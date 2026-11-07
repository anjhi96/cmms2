<?php

namespace App\Services\WeeklyPlan;

use App\Models\PMSchedule;
use App\Models\WeeklyPmPlan;
use Illuminate\Support\Facades\DB;

/**
 * Copies the weekly plan's PICs into pm_schedules.pic on request (the
 * "Terapkan PIC ke PM Schedule" button). Can run any number of times; each
 * run brings PM Schedule in line with the plan as it is now. Taking a
 * machine out of the plan restores its PIC immediately on save (see
 * releaseRemoved()), without waiting for the button.
 *
 *  - a planned machine with a PIC gets that PIC;
 *  - a machine this plan assigned earlier but that is no longer planned (or
 *    whose PIC was cleared in the plan) gets back the PIC it had before;
 *  - PMs already started or finished are never touched;
 *  - a PIC someone changed by hand in PM Schedule after our last run is left
 *    alone when the machine leaves the plan.
 *
 * plan.applied_pics remembers what this plan wrote and what was there before.
 */
class PicSyncService
{
    public const LOCKED_STATUSES = ['IN_PROGRESS', ...PMSchedule::DONE_STATUSES];

    /**
     * What a run would change, without changing anything. Kinds:
     * set (PIC was empty or ours), replace (someone else's PIC), restore
     * (machine left the plan, back to its old PIC), locked (started or
     * finished, skipped), changed (PIC edited by hand since, skipped).
     *
     * @return list<array{kind: string, id: int, machine: string, from: ?string, to: ?string}>
     */
    public function preview(WeeklyPmPlan $plan): array
    {
        $plan->loadMissing('items.pmSchedule');
        $applied = $plan->applied_pics ?? [];
        $actions = [];
        $planned = [];

        foreach ($plan->items as $item) {
            $schedule = $item->pmSchedule;

            if (! $schedule || blank($item->pic)) {
                continue;
            }

            $planned[$schedule->id] = true;
            $current = blank($schedule->pic) ? null : $schedule->pic;

            if ($current === $item->pic) {
                continue;
            }

            $kind = in_array($schedule->status, self::LOCKED_STATUSES, true)
                ? 'locked'
                : ($current === null || $current === ($applied[$schedule->id]['pic'] ?? null) ? 'set' : 'replace');

            $actions[] = ['kind' => $kind, 'id' => $schedule->id, 'machine' => $schedule->machine_number, 'from' => $current, 'to' => $item->pic];
        }

        $leaving = array_diff_key($applied, $planned);
        $schedules = PMSchedule::whereIn('id', array_keys($leaving))->get()->keyBy('id');

        foreach ($leaving as $id => $entry) {
            $schedule = $schedules[$id] ?? null;

            if (! $schedule) {
                continue;
            }

            $current = blank($schedule->pic) ? null : $schedule->pic;
            $previous = $entry['previous'] ?? null;

            if ($current === $previous) {
                continue;
            }

            $kind = match (true) {
                in_array($schedule->status, self::LOCKED_STATUSES, true) => 'locked',
                $current !== ($entry['pic'] ?? null) => 'changed',
                default => 'restore',
            };

            $actions[] = ['kind' => $kind, 'id' => (int) $id, 'machine' => $schedule->machine_number, 'from' => $current, 'to' => $previous];
        }

        return $actions;
    }

    /** @return array{set: int, replace: int, restore: int, locked: int, changed: int, pending: int} */
    public function summary(array $actions): array
    {
        $counts = array_fill_keys(['set', 'replace', 'restore', 'locked', 'changed'], 0);

        foreach ($actions as $action) {
            $counts[$action['kind']]++;
        }

        return $counts + ['pending' => $counts['set'] + $counts['replace'] + $counts['restore']];
    }

    /** @return array{set: int, replace: int, restore: int, locked: int, changed: int, pending: int} */
    public function apply(WeeklyPmPlan $plan): array
    {
        return $this->run($plan, ['set', 'replace', 'restore', 'locked', 'changed']);
    }

    /**
     * Runs right after every save of the plan: a machine that left the plan
     * (or whose PIC was cleared in it) gets its PM Schedule PIC back at once.
     * Writing new PICs still waits for the "Terapkan PIC" button.
     *
     * @return int number of PM Schedule PICs restored
     */
    public function releaseRemoved(WeeklyPmPlan $plan): int
    {
        if (empty($plan->applied_pics)) {
            return 0;
        }

        return $this->run($plan, ['restore', 'changed'], touchAppliedAt: false)['restore'];
    }

    /** @param  list<string>  $kinds  which preview actions to carry out */
    private function run(WeeklyPmPlan $plan, array $kinds, bool $touchAppliedAt = true): array
    {
        return DB::transaction(function () use ($plan, $kinds, $touchAppliedAt) {
            $plan->load('items.pmSchedule');
            $actions = array_values(array_filter($this->preview($plan), fn ($a) => in_array($a['kind'], $kinds, true)));
            $applied = $plan->applied_pics ?? [];

            foreach ($actions as $action) {
                if (in_array($action['kind'], ['set', 'replace'], true)) {
                    PMSchedule::whereKey($action['id'])->update(['pic' => $action['to']]);
                    // Keep the PIC from before this plan's first write, so leaving restores it.
                    $applied[$action['id']] = [
                        'pic' => $action['to'],
                        'previous' => array_key_exists($action['id'], $applied) ? $applied[$action['id']]['previous'] : $action['from'],
                    ];
                } elseif ($action['kind'] === 'restore') {
                    PMSchedule::whereKey($action['id'])->update(['pic' => $action['to']]);
                    unset($applied[$action['id']]);
                } elseif ($action['kind'] === 'changed') {
                    unset($applied[$action['id']]); // someone else owns this PIC now
                }
            }

            $plan->update($touchAppliedAt
                ? ['applied_pics' => $applied, 'pics_applied_at' => now()]
                : ['applied_pics' => $applied]);

            return $this->summary($actions);
        });
    }
}
