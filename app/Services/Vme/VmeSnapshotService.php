<?php

namespace App\Services\Vme;

use App\Models\Greasing;
use App\Models\JobType;
use App\Models\PicAvailability;
use App\Models\PMSchedule;
use App\Models\User;
use App\Models\VmeCharacterProfile;
use App\Services\ActiveActivityResolver;
use App\Support\Activities\ActiveActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the read-only VME snapshot: one entry per active PIC with their
 * ACTUAL current activity (from ActiveActivityResolver — never from the PM
 * schedule) and where that activity takes place on the map.
 *
 * It only reads. No operational record is written.
 */
class VmeSnapshotService
{
    private const CACHE_KEY = 'vme:snapshot:v1';

    /** Sources matched to a PIC by NAME only (pm_schedules.pic / greasings.pic). */
    private const NAME_MATCHED = ['PM', 'GREASING'];

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Unscoped snapshot, cached briefly so polling never hammers shared hosting. */
    public function cached(): array
    {
        $ttl = (int) config('vme.snapshot_ttl');

        return $ttl > 0
            ? Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->build())
            : $this->build();
    }

    /**
     * The snapshot a given viewer may see: PICs of allowed areas only, and
     * a version hash that changes only when the visible data changes (used as ETag).
     *
     * @param  list<int>|null  $allowedAreaIds
     */
    public function forViewer(?array $allowedAreaIds, bool $isAdmin): array
    {
        $snapshot = $this->cached();

        if ($allowedAreaIds !== null) {
            $snapshot['pics'] = array_values(array_filter(
                $snapshot['pics'],
                fn (array $pic) => $pic['area_id'] !== null && in_array($pic['area_id'], $allowedAreaIds, true),
            ));
        }

        // Only machines a visible PIC is working on, as a list keyed by nothing
        // (machine id is inside each entry and is the client's object key).
        $inUse = array_flip(array_filter(array_map(fn (array $pic) => $pic['target']['machine_id'] ?? null, $snapshot['pics'])));
        $snapshot['machines'] = array_values(array_intersect_key($snapshot['machines'], $inUse));

        $groupsInUse = array_flip(array_filter(array_map(fn (array $pic) => $pic['target']['group_id'] ?? null, $snapshot['pics'])));
        $snapshot['groups'] = array_values(array_intersect_key($snapshot['groups'], $groupsInUse));

        $oilInUse = array_flip(array_filter(array_map(
            fn (array $pic) => ($pic['target']['kind'] ?? null) === 'oil_audit' ? $pic['target']['area_id'] : null,
            $snapshot['pics'],
        )));
        $snapshot['oil_stations'] = array_values(array_intersect_key($snapshot['oil_stations'], $oilInUse));

        if (! $isAdmin) {
            unset($snapshot['sync']['unmapped_count']);
        }

        $generatedAt = $snapshot['generated_at'];
        unset($snapshot['generated_at']);
        $snapshot['version'] = sha1(json_encode($snapshot));
        $snapshot['generated_at'] = $generatedAt;

        return $snapshot;
    }

    public function build(): array
    {
        $features = VmeFeature::all();
        $syncOn = $features[VmeFeature::ACTIVITY_SYNC];
        $layout = VmeLayout::make();
        $resolver = new ActiveActivityResolver;

        $pics = User::query()
            ->select(['id', 'name', 'role', 'area_id', 'oil_audit_started_at', 'oil_audit_action_started_at'])
            ->where('role', User::ROLE_PIC)
            ->where('is_active', true)
            ->orderBy('name')->orderBy('id')
            ->get();

        $profiles = VmeCharacterProfile::query()->pluck('avatar_key', 'user_id');
        $animations = JobType::query()->pluck('animation_key', 'key');
        $inactive = PicAvailability::query()->whereIn('user_id', $pics->pluck('id'))
            ->whereDate('date', today())->pluck('reason', 'user_id');
        $ambiguous = $this->ambiguousNames();
        $finished = $syncOn ? $this->finishedToday($pics, $ambiguous) : [];

        $avatars = array_keys(config('vme.avatars'));
        $unmapped = 0;
        $states = [];

        // Pass 1: each PIC's actual current activity (and what just ended).
        foreach ($pics as $pic) {
            $current = null;
            $lastEnded = null;
            $status = 'IDLE';

            if ($inactive->has($pic->id)) {
                $status = 'INACTIVE';
            } elseif ($syncOn) {
                $all = $resolver->forToday($pic);

                if (isset($ambiguous[$this->normalizeName($pic->name)])) {
                    $kept = $all->reject(fn (ActiveActivity $a) => in_array($a->source, self::NAME_MATCHED, true))->values();
                    $unmapped += $all->count() - $kept->count();
                    $all = $kept;
                }

                $current = $all->first(fn (ActiveActivity $a) => ! $a->isFinished());

                if ($current) {
                    $status = 'WORKING';
                } else {
                    $lastEnded = $this->lastEnded($all, $finished[$this->normalizeName($pic->name)] ?? []);
                }
            }

            $states[] = compact('pic', 'current', 'lastEnded', 'status');
        }

        // Only the machines somebody is working on right now are loaded —
        // never the whole Machine Master.
        $layout->preloadMachines(array_values(array_filter(array_map(
            fn (array $st) => $st['current']?->machineId,
            $states,
        ))));

        // Pass 2: positions, targets, and the distinct machines in use.
        $baseIndex = [];
        $machines = [];
        $groups = [];
        $oilStations = [];
        $out = [];

        foreach ($states as ['pic' => $pic, 'current' => $current, 'lastEnded' => $lastEnded, 'status' => $status]) {
            $zoneKey = $layout->zoneKey($pic->area_id);
            $index = $baseIndex[$zoneKey] = ($baseIndex[$zoneKey] ?? -1) + 1;
            $base = $layout->basePoint($pic->area_id, $index);

            $activity = null;
            $target = null;

            if ($current) {
                $target = $this->targetFor($current, $pic, $layout);
                $target = $this->withPatrol($target, $current, $pic, $layout);
                $animation = $animations[$current->jobTypeKey] ?? config('vme.fallback_animation');
                $activity = [
                    'source' => $current->source,
                    'record_id' => $current->recordId,
                    'status' => $current->status,
                    'job_type_key' => $current->jobTypeKey,
                    'animation_key' => in_array($animation, config('vme.animations'), true) ? $animation : config('vme.fallback_animation'),
                    'label' => $current->displayLabel(),
                    'location' => $current->locationLabel(),
                    'machine_id' => $current->machineId,
                    'group_id' => $current->groupId,
                    'area_id' => $current->areaId,
                    'started_at' => $current->startedAt->toIso8601String(),
                ];

                // Group markers and Oil Audit stations exist only while somebody uses them.
                if ($target['kind'] === 'group' && ! isset($groups[$target['group_id']])) {
                    $g = $layout->groupObject($target['group_id']);
                    $groups[$g['id']] = ['id' => $g['id'], 'name' => $g['name'], 'area_id' => $g['area_id'], 'x' => $g['x'], 'y' => $g['y']];
                }
                if ($target['kind'] === 'oil_audit' && ! isset($oilStations[$target['area_id']])) {
                    $o = $layout->oilStationObject($target['area_id']);
                    $oilStations[$target['area_id']] = ['area_id' => $target['area_id'], 'x' => $o['x'], 'y' => $o['y']];
                }

                if ($target['kind'] === 'machine' && ! isset($machines[$target['machine_id']])) {
                    $obj = $layout->machineObject($target['machine_id']);
                    $machines[$target['machine_id']] = [
                        'id' => $obj['id'],
                        'number' => $obj['number'],
                        'area_id' => $obj['area_id'],
                        'zone' => $layout->zoneKey($obj['area_id']),
                        'x' => $obj['x'],
                        'y' => $obj['y'],
                    ];
                }
            }

            $out[] = [
                'user_id' => $pic->id,
                'name' => $pic->name,
                'avatar_key' => $this->avatarFor($pic->id, $profiles->get($pic->id), $avatars),
                'area_id' => $pic->area_id,
                'status' => $status,
                'zone' => $base['zone'],
                'base' => ['x' => $base['x'], 'y' => $base['y']],
                'activity' => $activity,
                'target' => $target,
                'last_ended' => $lastEnded,
            ];
        }

        $out = $this->spreadSharedTargets($out);

        return [
            'generated_at' => now()->toIso8601String(),
            'features' => $features,
            'sync' => [
                'enabled' => $syncOn,
                'status' => $syncOn ? 'ok' : 'disabled',
                'unmapped_count' => $unmapped,
            ],
            'pics' => $out,
            'machines' => $machines,
            'groups' => $groups,
            'oil_stations' => $oilStations,
        ];
    }

    /**
     * Where the activity is performed. PM/Manual -> its machine (saved or
     * default-grid position); Greasing -> its group anchor; Oil Audit (no
     * machine) -> the area's Oil Audit station; anything without a usable
     * location -> the area default anchor, or the PIC's base when even the
     * area is unknown.
     */
    private function targetFor(ActiveActivity $activity, User $pic, VmeLayout $layout): array
    {
        $areaId = $activity->areaId ?? $pic->area_id;

        $resolved = null;

        // Manual Activity located in the Workshop: the PIC really logged it there.
        if ($activity->source === 'MANUAL' && ! $activity->machineId && $this->isWorkshopLabel($activity->machineNumber)) {
            $point = $layout->workshopPoint();

            return ['kind' => 'workshop', 'x' => $point['x'], 'y' => $point['y'], 'zone' => 'workshop', 'face' => 'right',
                'area_id' => null, 'machine_id' => null, 'group_id' => null];
        }

        // Manual Activity whose location text is a Group name (e.g. "WWD 02") goes to that Group's anchor.
        $groupId = $activity->groupId;
        if (! $groupId && $activity->source === 'MANUAL' && ! $activity->machineId) {
            $groupId = $layout->groupIdByName($activity->machineNumber);
        }

        if (in_array($activity->source, ['OIL_AUDIT', 'OIL_AUDIT_ACTION'], true)) {
            $obj = $pic->area_id ? $layout->oilStationObject($pic->area_id) : null;
            $resolved = $obj ? ['kind' => 'oil_audit', 'obj' => $obj, 'area_id' => $pic->area_id] : null;
        } elseif ($groupId) {
            $obj = $layout->groupObject($groupId);
            $resolved = $obj ? ['kind' => 'group', 'obj' => $obj, 'area_id' => $obj['area_id'], 'group_id' => $groupId] : null;
        } elseif ($activity->machineId) {
            $obj = $layout->machineObject($activity->machineId);
            $resolved = $obj
                ? ['kind' => 'machine', 'obj' => $obj, 'area_id' => $obj['area_id'], 'machine_id' => $activity->machineId]
                : null;
        }

        if ($resolved === null && $areaId) {
            $obj = $layout->areaDefaultObject($areaId);
            $resolved = $obj ? ['kind' => 'area_default', 'obj' => $obj, 'area_id' => $areaId] : null;
        }

        if ($resolved === null) {
            $base = $layout->basePoint(null, 0);

            return ['kind' => 'base', 'x' => $base['x'], 'y' => $base['y'], 'zone' => $base['zone'], 'face' => 'right',
                'area_id' => null, 'machine_id' => null, 'group_id' => null];
        }

        $stand = $layout->standPoint($resolved['obj']);

        return [
            'kind' => $resolved['kind'],
            'x' => $stand['x'],
            'y' => $stand['y'],
            'zone' => $layout->zoneKey($resolved['area_id']),
            // The character stands beside the object and looks at it.
            'face' => $stand['x'] >= $resolved['obj']['x'] ? 'left' : 'right',
            'area_id' => $resolved['area_id'],
            'machine_id' => $resolved['machine_id'] ?? null,
            'group_id' => $resolved['group_id'] ?? null,
        ];
    }

    /**
     * Work done by walking around the Area (config 'patrol_job_types', e.g. Oil
     * Audit) gets a patrol route. Only when there is no machine/group to stay
     * at: the route starts where the character would otherwise have stood.
     *
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    private function withPatrol(array $target, ActiveActivity $activity, User $pic, VmeLayout $layout): array
    {
        if (! in_array($activity->jobTypeKey, config('vme.patrol_job_types'), true)
            || ! in_array($target['kind'], ['oil_audit', 'area_default'], true)
            || ! $layout->hasArea($target['area_id'])) {
            return $target;
        }

        $route = $layout->patrolPoints($target['area_id'], $pic->id);

        return array_merge($target, ['x' => $route[0]['x'], 'y' => $route[0]['y'], 'face' => 'right', 'patrol' => $route]);
    }

    private function isWorkshopLabel(?string $label): bool
    {
        $needle = mb_strtolower(trim((string) $label));

        if ($needle === '') {
            return false;
        }

        foreach (config('vme.workshop_labels') as $word) {
            if ($needle === $word || str_starts_with($needle, $word.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Several PICs sharing one destination (same machine, group, Workshop or
     * area anchor) would be drawn on top of each other and all but one
     * would be hidden. Fan them out in a small, deterministic grid (ordered
     * by user id) around the shared point.
     *
     * @param  list<array<string, mixed>>  $pics
     * @return list<array<string, mixed>>
     */
    private function spreadSharedTargets(array $pics): array
    {
        $groups = [];

        foreach ($pics as $i => $pic) {
            // A patrolling PIC follows its own rotated route; it is not fanned out.
            if ($pic['target'] !== null && ! isset($pic['target']['patrol'])) {
                $t = $pic['target'];
                $groups[$t['zone'].'|'.$t['x'].'|'.$t['y']][] = $i;
            }
        }

        foreach ($groups as $indexes) {
            $n = count($indexes);

            if ($n < 2) {
                continue;
            }

            usort($indexes, fn ($a, $b) => $pics[$a]['user_id'] <=> $pics[$b]['user_id']);
            $perRow = min($n, 4);

            foreach ($indexes as $slot => $i) {
                $col = $slot % 4;
                $row = intdiv($slot, 4);
                $pics[$i]['target']['x'] += (int) round(($col - ($perRow - 1) / 2) * 30);
                $pics[$i]['target']['y'] += $row * 18;
            }
        }

        return $pics;
    }

    /**
     * Most recent ended activity today, so the client can tell
     * CLOSED_BY_MONITOR (removed from the monitor; the work was NOT
     * completed) from FINISHED (the source module marked it done).
     *
     * @param  list<array{source: string, record_id: int|null, at: Carbon}>  $finished
     */
    private function lastEnded($all, array $finished): ?array
    {
        $candidates = $finished;

        foreach ($all as $a) {
            if ($a->isFinished()) {
                $candidates[] = ['source' => $a->source, 'record_id' => $a->recordId, 'at' => Carbon::instance($a->endedAt), 'reason' => ActiveActivity::STATUS_CLOSED_BY_MONITOR];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($x, $y) => $y['at']->getTimestamp() <=> $x['at']->getTimestamp());
        $latest = $candidates[0];

        return [
            'source' => $latest['source'],
            'record_id' => $latest['record_id'],
            'reason' => $latest['reason'] ?? ActiveActivity::STATUS_FINISHED,
            'at' => $latest['at']->toIso8601String(),
        ];
    }

    /**
     * PM / Greasing the source module marked done today, keyed by
     * normalised PIC name. Names that are ambiguous are skipped.
     *
     * @return array<string, list<array{source: string, record_id: int, at: Carbon, reason: string}>>
     */
    private function finishedToday($pics, array $ambiguous): array
    {
        $names = $pics->pluck('name')->filter(fn ($n) => ! isset($ambiguous[$this->normalizeName($n)]))->values();
        $map = [];

        if ($names->isEmpty()) {
            return $map;
        }

        PMSchedule::query()
            ->whereIn('pic', $names)->whereNotNull('start_time')
            ->whereDate('actual_date', today())->whereIn('status', PMSchedule::DONE_STATUSES)
            ->get(['id', 'pic', 'actual_date', 'start_time', 'end_time'])
            ->each(function (PMSchedule $pm) use (&$map) {
                $date = $pm->actual_date instanceof Carbon ? $pm->actual_date->toDateString() : (string) $pm->actual_date;
                $time = filled($pm->end_time) ? $pm->end_time : $pm->start_time;
                $map[$this->normalizeName($pm->pic)][] = [
                    'source' => 'PM', 'record_id' => $pm->id, 'at' => Carbon::parse($date.' '.$time), 'reason' => ActiveActivity::STATUS_FINISHED,
                ];
            });

        Greasing::query()
            ->whereIn('pic', $names)->whereNotNull('start_time')
            ->whereDate('start_time', today())->whereIn('status', Greasing::DONE_STATUSES)
            ->get(['id', 'pic', 'start_time', 'updated_at'])
            ->each(function (Greasing $g) use (&$map) {
                $map[$this->normalizeName($g->pic)][] = [
                    'source' => 'GREASING', 'record_id' => $g->id, 'at' => Carbon::instance($g->updated_at ?? $g->start_time), 'reason' => ActiveActivity::STATUS_FINISHED,
                ];
            });

        return $map;
    }

    /**
     * PIC names shared by more than one PIC account (any status). PM and
     * Greasing identify their PIC by name only, so for these names the PIC
     * of a record cannot be determined — VME drops them rather than guess.
     *
     * @return array<string, list<int>> normalised name => user ids
     */
    public function ambiguousNames(): array
    {
        $byName = [];

        User::query()->where('role', User::ROLE_PIC)->get(['id', 'name'])
            ->each(function (User $u) use (&$byName) {
                $byName[$this->normalizeName($u->name)][] = $u->id;
            });

        return array_filter($byName, fn (array $ids) => count($ids) > 1);
    }

    /**
     * Admin diagnostics: today's started PM / Greasing records VME cannot
     * attach to exactly one PIC.
     *
     * @return array{ambiguous: list<array{name: string, user_ids: list<int>}>, orphans: list<array{source: string, record_id: int, pic: string}>}
     */
    public function diagnostics(): array
    {
        $ambiguous = $this->ambiguousNames();
        $known = [];
        User::query()->where('role', User::ROLE_PIC)->get(['name'])
            ->each(function (User $u) use (&$known) {
                $known[$this->normalizeName($u->name)] = true;
            });

        $orphans = [];

        PMSchedule::query()->whereNotNull('start_time')->whereDate('actual_date', today())
            ->whereNotIn('status', PMSchedule::DONE_STATUSES)->whereNotNull('pic')
            ->get(['id', 'pic'])->each(function (PMSchedule $pm) use ($known, &$orphans) {
                if (! isset($known[$this->normalizeName($pm->pic)])) {
                    $orphans[] = ['source' => 'PM', 'record_id' => $pm->id, 'pic' => $pm->pic];
                }
            });

        Greasing::query()->whereNotNull('start_time')->whereDate('start_time', today())
            ->whereNotIn('status', Greasing::DONE_STATUSES)->whereNotNull('pic')
            ->get(['id', 'pic'])->each(function (Greasing $g) use ($known, &$orphans) {
                if (! isset($known[$this->normalizeName($g->pic)])) {
                    $orphans[] = ['source' => 'GREASING', 'record_id' => $g->id, 'pic' => $g->pic];
                }
            });

        return [
            'ambiguous' => collect($ambiguous)->map(fn ($ids, $name) => ['name' => $name, 'user_ids' => $ids])->values()->all(),
            'orphans' => $orphans,
        ];
    }

    public function normalizeName(?string $name): string
    {
        return JobType::normalize((string) $name);
    }

    /** Configured avatar, else a stable preset derived from the user id. */
    public function avatarFor(int $userId, ?string $stored, array $avatars): string
    {
        return $stored !== null && in_array($stored, $avatars, true)
            ? $stored
            : $avatars[$userId % count($avatars)];
    }
}
