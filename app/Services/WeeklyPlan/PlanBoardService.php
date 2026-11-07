<?php

namespace App\Services\WeeklyPlan;

use App\Models\Machine;
use App\Models\PicAvailability;
use App\Models\PMSchedule;
use App\Models\User;
use App\Models\WeeklyPmPlan;
use App\Models\WeeklyPmPlanItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the planner board needs for one area + week: the machines
 * already placed on a day, the pool of PM Schedule rows still waiting, the
 * area's PICs and which of them are unavailable on which day.
 */
class PlanBoardService
{
    /**
     * Open PM Schedule rows of the area that can be planned this week: not
     * finished, planned up to the end of the month the week ends in. Older
     * open rows stay in so overdue machines are never lost.
     *
     * @return Collection<int, PMSchedule>
     */
    public function candidates(WeeklyPmPlan $plan): Collection
    {
        return PMSchedule::query()
            ->where('area', $plan->area)
            ->whereNotIn('status', PMSchedule::DONE_STATUSES)
            ->whereDate('plan_date', '<=', $plan->weekEnd()->endOfMonth()->toDateString())
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('plan_date')
            ->orderBy('machine_number')
            ->get();
    }

    /** @return list<string> PIC names of the area, alphabetical. */
    public function picNames(string $area): array
    {
        return User::query()
            ->where('role', User::ROLE_PIC)
            ->whereHas('area', fn ($q) => $q->where('name', $area))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /** @return array<string, array<string, string>> [Y-m-d => [pic name => reason label]] */
    public function unavailable(WeeklyPmPlan $plan, array $picNames): array
    {
        $map = [];

        PicAvailability::query()
            ->with('user:id,name')
            ->whereBetween('date', [$plan->week_start->toDateString(), $plan->weekEnd()->toDateString()])
            ->whereHas('user', fn ($q) => $q->whereIn('name', $picNames))
            ->get()
            ->each(function (PicAvailability $row) use (&$map) {
                $map[$row->date->toDateString()][$row->user->name] = $row->label();
            });

        return $map;
    }

    /**
     * The last construction note written for each machine in any earlier
     * week, so the coordinator does not retype "0.225 ST" every week.
     *
     * @param  list<int>  $machineIds
     * @return array<int, string>
     */
    public function lastNotes(WeeklyPmPlan $plan, array $machineIds): array
    {
        if ($machineIds === []) {
            return [];
        }

        return WeeklyPmPlanItem::query()
            ->whereIn('machine_id', $machineIds)
            ->whereNotNull('construction_note')
            ->where('construction_note', '!=', '')
            ->where('day', '<', $plan->week_start->toDateString())
            ->orderByDesc('day')
            ->orderByDesc('id')
            ->get(['machine_id', 'construction_note'])
            ->unique('machine_id')
            ->pluck('construction_note', 'machine_id')
            ->all();
    }

    /** @return list<int> PM Schedule ids planned last week and still not finished. */
    public function carriedOverIds(WeeklyPmPlan $plan): array
    {
        $previous = WeeklyPmPlan::query()
            ->where('area', $plan->area)
            ->whereDate('week_start', $plan->week_start->subWeek()->toDateString())
            ->first();

        if (! $previous) {
            return [];
        }

        return $previous->items()
            ->whereHas('pmSchedule', fn ($q) => $q->whereNotIn('status', PMSchedule::DONE_STATUSES))
            ->pluck('pm_schedule_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** The full board state sent to the browser. */
    public function state(WeeklyPmPlan $plan): array
    {
        $plan->load('items.pmSchedule');
        $candidates = $this->candidates($plan);
        $placedIds = array_flip($plan->items->pluck('pm_schedule_id')->filter()->map(fn ($id) => (int) $id)->all());

        $machineIds = $candidates->pluck('machine_id')->merge($plan->items->pluck('machine_id'))->filter()->unique()->values()->all();
        $notes = $this->lastNotes($plan, $machineIds);
        $carried = array_flip($this->carriedOverIds($plan));
        $picNames = $this->picNames($plan->area);

        $pool = $candidates
            ->reject(fn (PMSchedule $s) => isset($placedIds[(int) $s->id]))
            ->map(fn (PMSchedule $s) => $this->card($plan, $s, [
                'day' => null,
                'pic' => $s->pic,
                'construction_note' => $notes[$s->machine_id] ?? null,
                'carried_over' => isset($carried[$s->id]),
            ]))
            ->values();

        $items = $plan->items->map(fn (WeeklyPmPlanItem $item) => array_merge(
            $item->pmSchedule
                ? $this->card($plan, $item->pmSchedule, ['carried_over' => isset($carried[$item->pm_schedule_id])])
                : ['pm_schedule_id' => null, 'machine_id' => $item->machine_id, 'due_date' => null, 'plan_date' => null, 'overdue' => false, 'due_this_week' => false, 'carried_over' => false, 'status' => null],
            [
                'machine_number' => $item->machine_number,
                'machine_type' => $item->machine_type,
                'type_family' => MachineFamily::of($item->machine_type),
                'day' => $item->day->toDateString(),
                'pic' => $item->pic,
                'construction_note' => $item->construction_note,
            ]
        ))->values();

        return [
            'plan' => [
                'id' => $plan->id,
                'area' => $plan->area,
                'week_start' => $plan->week_start->toDateString(),
                'status' => $plan->status,
                'sent_at' => $plan->sent_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            ],
            'days' => array_map(fn ($d) => [
                'date' => $d->toDateString(),
                'name' => IndoDate::dayName($d),
                'label' => IndoDate::dayDate($d),
                'limit' => $plan->dayLimit($d->toDateString()),
                'types' => $plan->dayTypes($d->toDateString()),
            ], $plan->workDays()),
            'machine_types' => $this->machineTypes($plan, $candidates),
            'max_day_limit' => WeeklyPmPlan::MAX_DAY_LIMIT,
            'items' => $items,
            'pool' => $pool,
            'pics' => $picNames,
            'unavailable' => (object) $this->unavailable($plan, $picNames),
        ];
    }

    /** @return list<string> machine families of the area (first word of the type), for the per-day mix */
    public function machineTypes(WeeklyPmPlan $plan, ?Collection $candidates = null): array
    {
        return Machine::query()
            ->where('area', $plan->area)
            ->distinct()
            ->pluck('machine_type')
            ->merge(($candidates ?? $this->candidates($plan))->pluck('machine_type'))
            ->map(fn ($type) => MachineFamily::of($type))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function card(WeeklyPmPlan $plan, PMSchedule $s, array $extra): array
    {
        $planDate = self::date($s->plan_date);
        $dueDate = self::date($s->due_date);
        $due = $dueDate ?? $planDate;

        return array_merge([
            'pm_schedule_id' => (int) $s->id,
            'machine_id' => $s->machine_id !== null ? (int) $s->machine_id : null,
            'machine_number' => $s->machine_number,
            'machine_type' => $s->machine_type,
            'type_family' => MachineFamily::of($s->machine_type),
            'plan_date' => $planDate?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'status' => $s->status,
            'overdue' => $due !== null && $due->lt($plan->week_start),
            'due_this_week' => $due !== null && $due->lte($plan->weekEnd()),
        ], $extra);
    }

    /** pm_schedules dates are not cast on the model, so they arrive as strings. */
    public static function date(mixed $value): ?CarbonImmutable
    {
        return blank($value) ? null : CarbonImmutable::parse($value)->startOfDay();
    }
}
