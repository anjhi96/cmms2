<?php

namespace App\Services;

use App\Models\ActivityMonitorClosure;
use App\Models\Greasing;
use App\Models\Machine;
use App\Models\ManualActivity;
use App\Models\PMSchedule;
use App\Models\SmallPmRow;
use App\Models\SmallPmSchedule;
use App\Models\User;
use App\Services\Vme\JobTypeResolver;
use App\Support\Activities\ActiveActivity;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "what activity does this PIC currently have
 * open?" across every supported activity source. The rule the whole app
 * relies on: a PIC may only ever have ONE active activity at a time.
 *
 * Everything here is DERIVED from existing records/columns — there is no
 * activity table:
 *
 *   PM               pm_schedules.start_time (the column START PM writes) with
 *                    actual_date = today, status not finished
 *   Greasing         greasings.start_time (timestamp), started today, status not finished
 *   Small PM         small_pm_schedules.start_time, started today, status not finished
 *   Oil Audit        users.oil_audit_started_at (daily marker, today)
 *   Oil Audit Action users.oil_audit_action_started_at (daily marker, today)
 *   Action Predictive users.predictive_started_at (daily marker, today)
 *   Manual Activity  manual_activities.started_at (owned by the user, today)
 *
 * "Started today" is enforced at SQL level against the source's real START
 * field, never inferred from actual_date/action_date/updated_at/audited_at
 * alone.
 *
 * Day rollover is also derived: an activity whose start date is not "today"
 * is simply not returned here. No source module status is ever changed.
 *
 * "Finish" (the Activity Control Panel) is monitoring-only: it writes a row
 * to activity_monitor_closures. Such an activity is still returned by
 * {@see forToday()} (so "Started Today" lists it as FINISHED) but is marked
 * $endedAt, so {@see currentFor()} skips it and it disappears from /monitor.
 * The source module (pm_schedules / greasings / users) is never touched.
 *
 * Technical limitation: PM and Greasing store the PIC as a name string
 * (`pic` column) with no user_id relation, so those two sources are matched
 * by `$user->name`. Oil Audit / Oil Audit Action / Manual use the user id.
 * Unifying the PIC model is out of scope (a repo-wide change).
 */
class ActiveActivityResolver
{
    private ?JobTypeResolver $jobTypes = null;

    /** @var array<int, int>|null user id => index, PICs with Small PM in progress today */
    private ?array $smallPmPicIds = null;

    private function jobTypeKey(string $source, ?string $name = null): string
    {
        return ($this->jobTypes ??= new JobTypeResolver)->keyFor($source, $name);
    }

    /**
     * Every activity the PIC started TODAY, newest start first. Includes
     * activities finished from the control panel (marked $endedAt) so
     * "Started Today" can list them; use {@see currentFor()} for the one
     * that is actually active.
     *
     * @return Collection<int, ActiveActivity>
     */
    public function forToday(User $user): Collection
    {
        $closures = $this->closuresFor($user);

        return collect([
            ...$this->pmActivities($user, $closures),
            ...$this->greasingActivities($user, $closures),
            ...$this->smallPmActivities($user, $closures),
            ...$this->oilAuditActivity($user, $closures),
            ...$this->oilAuditActionActivity($user, $closures),
            ...$this->predictiveActivity($user, $closures),
            ...$this->manualActivities($user, $closures),
        ])
            ->sortByDesc(fn (ActiveActivity $a) => $a->startedAt->getTimestamp())
            ->values();
    }

    /**
     * The PIC's single current active activity, or null. An activity that
     * was finished on the monitor is skipped.
     */
    public function currentFor(User $user): ?ActiveActivity
    {
        return $this->forToday($user)
            ->reject(fn (ActiveActivity $a) => $a->isFinished())
            ->first();
    }

    /**
     * Today's monitor closures for this PIC, keyed "SOURCE:source_key".
     *
     * @return Collection<string, CarbonInterface>
     */
    private function closuresFor(User $user): Collection
    {
        return ActivityMonitorClosure::query()
            ->where('pic_user_id', $user->id)
            ->whereDate('business_date', today())
            ->get()
            ->mapWithKeys(fn (ActivityMonitorClosure $c) => [
                ActivityMonitorClosure::keyFor($c->source, $c->source_key) => $c->closed_at,
            ]);
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function pmActivities(User $user, Collection $closures): array
    {
        if (blank($user->name)) {
            return [];
        }

        return PMSchedule::query()
            ->activeActivity()                       // start_time set, status not finished
            ->where('pic', $user->name)
            ->whereNotNull('actual_date')            // a real start date, not just start_time
            ->whereDate('actual_date', today())      // started today (app timezone)
            ->get()
            ->map(function (PMSchedule $pm) use ($user, $closures): ?ActiveActivity {
                $startedAt = $pm->startedAt();

                if (! $startedAt || ! $startedAt->isToday()) {
                    return null;
                }

                return new ActiveActivity(
                    source: 'PM',
                    label: 'PM',
                    picName: $user->name,
                    startedAt: $startedAt,
                    machineNumber: $pm->machine_number,
                    recordId: $pm->id,
                    endedAt: $closures->get(ActivityMonitorClosure::keyFor('PM', $pm->id)),
                    userId: $user->id,
                    areaId: $user->area_id,
                    machineId: $pm->machine_id ? (int) $pm->machine_id : null,
                    jobTypeKey: $this->jobTypeKey('PM'),
                );
            })
            ->filter()
            ->all();
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function greasingActivities(User $user, Collection $closures): array
    {
        if (blank($user->name)) {
            return [];
        }

        return Greasing::query()
            ->activeActivity()                       // start_time set, status not finished
            ->where('pic', $user->name)
            ->whereDate('start_time', today())       // started today (app timezone)
            ->with('group:id,name')
            ->get()
            ->map(function (Greasing $greasing) use ($user, $closures): ?ActiveActivity {
                $startedAt = $greasing->start_time;

                if (! $startedAt || ! $startedAt->isToday()) {
                    return null;
                }

                // Greasing is group-level, not machine-level.
                return new ActiveActivity(
                    source: 'GREASING',
                    label: 'Greasing',
                    picName: $user->name,
                    startedAt: $startedAt,
                    machineNumber: null,
                    recordId: $greasing->id,
                    groupName: $greasing->group?->name,
                    endedAt: $closures->get(ActivityMonitorClosure::keyFor('GREASING', $greasing->id)),
                    userId: $user->id,
                    areaId: $greasing->group?->area_id ?? $user->area_id,
                    groupId: $greasing->group_id ? (int) $greasing->group_id : null,
                    jobTypeKey: $this->jobTypeKey('GREASING'),
                );
            })
            ->filter()
            ->all();
    }

    /**
     * Small PM is row-level like Greasing, but the PIC is stored by user id.
     *
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function smallPmActivities(User $user, Collection $closures): array
    {
        // The monitor resolves every PIC with one resolver instance: one
        // lookup of who has Small PM running today saves a query per PIC.
        $this->smallPmPicIds ??= SmallPmSchedule::query()
            ->activeActivity()
            ->whereDate('start_time', today())
            ->distinct()
            ->pluck('pic_user_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        if (! isset($this->smallPmPicIds[$user->id])) {
            return [];
        }

        return SmallPmSchedule::query()
            ->activeActivity()
            ->where('pic_user_id', $user->id)
            ->whereDate('start_time', today())
            ->with(['row:id,name,area_id', 'row.machines:id,small_pm_row_id'])
            ->get()
            ->map(function (SmallPmSchedule $schedule) use ($user, $closures): ?ActiveActivity {
                $startedAt = $schedule->start_time;

                if (! $startedAt || ! $startedAt->isToday()) {
                    return null;
                }

                return new ActiveActivity(
                    source: 'SMALL_PM',
                    label: 'Small PM: '.(SmallPmRow::TASK_TYPES[$schedule->task_type] ?? $schedule->task_type),
                    picName: $user->name,
                    startedAt: $startedAt,
                    recordId: $schedule->id,
                    groupName: $schedule->row?->name,
                    endedAt: $closures->get(ActivityMonitorClosure::keyFor('SMALL_PM', $schedule->id)),
                    userId: $user->id,
                    areaId: $schedule->row?->area_id ?? $user->area_id,
                    // The row's first machine places the PIC on the VME map.
                    machineId: $schedule->row?->machines->first()?->id,
                    jobTypeKey: $this->jobTypeKey('SMALL_PM'),
                );
            })
            ->filter()
            ->all();
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function oilAuditActivity(User $user, Collection $closures): array
    {
        if (! $user->hasStartedOilAuditToday()) {
            return [];
        }

        return [new ActiveActivity(
            source: 'OIL_AUDIT',
            label: 'Oil Audit',
            picName: $user->name,
            startedAt: $user->oil_audit_started_at,
            recordId: null,
            endedAt: $closures->get(ActivityMonitorClosure::keyFor('OIL_AUDIT', $user->id)),
            userId: $user->id,
            areaId: $user->area_id,
            jobTypeKey: $this->jobTypeKey('OIL_AUDIT'),
        )];
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function oilAuditActionActivity(User $user, Collection $closures): array
    {
        if (! $user->hasStartedOilAuditActionToday()) {
            return [];
        }

        return [new ActiveActivity(
            source: 'OIL_AUDIT_ACTION',
            label: 'Oil Audit Action',
            picName: $user->name,
            startedAt: $user->oil_audit_action_started_at,
            recordId: null,
            endedAt: $closures->get(ActivityMonitorClosure::keyFor('OIL_AUDIT_ACTION', $user->id)),
            userId: $user->id,
            areaId: $user->area_id,
            jobTypeKey: $this->jobTypeKey('OIL_AUDIT_ACTION'),
        )];
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function predictiveActivity(User $user, Collection $closures): array
    {
        if (! $user->hasStartedPredictiveToday()) {
            return [];
        }

        return [new ActiveActivity(
            source: 'PREDICTIVE',
            label: 'Action Predictive',
            picName: $user->name,
            startedAt: $user->predictive_started_at,
            recordId: null,
            endedAt: $closures->get(ActivityMonitorClosure::keyFor('PREDICTIVE', $user->id)),
            userId: $user->id,
            areaId: $user->area_id,
            jobTypeKey: $this->jobTypeKey('PREDICTIVE'),
        )];
    }

    /**
     * @param  Collection<string, CarbonInterface>  $closures
     * @return array<int, ActiveActivity>
     */
    private function manualActivities(User $user, Collection $closures): array
    {
        return ManualActivity::query()
            ->startedOnFor($user->id)
            ->get()
            ->map(fn (ManualActivity $manual) => new ActiveActivity(
                source: 'MANUAL',
                label: 'Manual Activity',
                picName: $user->name,
                startedAt: $manual->started_at,
                title: $manual->name,
                machineNumber: $manual->machine_number,
                recordId: $manual->id,
                endedAt: $closures->get(ActivityMonitorClosure::keyFor('MANUAL', $manual->id)),
                userId: $user->id,
                areaId: $user->area_id,
                machineId: $this->machineIdFor($manual->machine_number),
                jobTypeKey: $this->jobTypeKey('MANUAL', $manual->name),
            ))
            ->all();
    }

    /**
     * Manual Activity stores a free-text machine label; only an exact
     * machines.machine_number match counts as a known machine.
     */
    private function machineIdFor(?string $machineNumber): ?int
    {
        if (blank($machineNumber)) {
            return null;
        }

        $id = Machine::query()->where('machine_number', trim($machineNumber))->value('id');

        return $id ? (int) $id : null;
    }
}
