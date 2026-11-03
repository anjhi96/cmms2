<?php

namespace App\Services\Vme;

use App\Models\Area;
use App\Models\Group;
use App\Models\Machine;
use App\Services\Vme\Ai\AiCharacterRegistry;
use Illuminate\Support\Collection;

/**
 * Geometry of the single VME world: Office + Workshop on top, a corridor,
 * and the Production zone below with one column per active Area.
 *
 * Everything is derived from master data (Area, Group, Machine) plus
 * vme_machine_positions, so adding an Area/Group/Machine needs no code
 * change. Coordinates are visual pixels (not physical locations); machine
 * positions are stored relative to their Area column.
 */
class VmeLayout
{
    private const DESK_COLUMNS = 4;

    /** @var array<string, mixed> */
    private array $cfg;

    /** @var Collection<int, Area> */
    private Collection $areas;

    /** @var Collection<int, Group> */
    private Collection $groups;

    /**
     * Machines are NOT loaded up front: only those an active PIC is working
     * on are fetched, through preloadMachines() / machineObject().
     *
     * @var array<int, array{id: int, number: string, area_id: int, x: int, y: int}|null>
     */
    private array $machines = [];

    /** @var array<int, int> area index (column) by area id */
    private array $areaIndex = [];

    /** @var array<int, int> group slot by group id */
    private array $groupSlot = [];

    private int $width;

    private int $height;

    /** AI characters that get a desk in the Office (drives the Office height). */
    private int $deskCount = 0;

    /**
     * @param  int|null  $onlyAreaId  an Area filter: the map then shows only that Area next to Office and Workshop
     */
    public static function make(?int $onlyAreaId = null): self
    {
        return new self($onlyAreaId);
    }

    public function __construct(private readonly ?int $onlyAreaId = null)
    {
        $this->cfg = config('vme.layout');

        $this->areas = Area::active()->orderBy('name')->when($onlyAreaId, fn ($q, $id) => $q->whereKey($id))->get(['id', 'name']);
        $this->deskCount = count(app(AiCharacterRegistry::class)->enabled($onlyAreaId));
        $areaIds = $this->areas->pluck('id');
        $this->groups = Group::query()->whereIn('area_id', $areaIds)->orderBy('name')->orderBy('id')->get(['id', 'name', 'area_id']);

        foreach ($this->areas->values() as $i => $area) {
            $this->areaIndex[$area->id] = $i;
        }

        // Slots for groups on the area's group band.
        $perArea = [];
        foreach ($this->groups as $group) {
            $this->groupSlot[$group->id] = $perArea[$group->area_id] = ($perArea[$group->area_id] ?? -1) + 1;
        }

        $c = $this->cfg;
        $this->width = max($c['min_width'], $this->areas->count() * $c['area_width'] + 2 * $c['margin']);
        $this->height = $this->productionTop() + $c['area_height'] + $c['margin'];
    }

    /** @return Collection<int, Area> */
    public function areas(): Collection
    {
        return $this->areas;
    }

    public function hasArea(?int $areaId): bool
    {
        return $areaId !== null && isset($this->areaIndex[$areaId]);
    }

    public function productionTop(): int
    {
        return $this->cfg['margin'] + $this->officeHeight() + $this->cfg['corridor_height'];
    }

    /** Office/Workshop height: the configured one, taller when the AI desks need more than one row. */
    public function officeHeight(): int
    {
        $rows = max(1, (int) ceil($this->deskCount / self::DESK_COLUMNS));

        return max($this->cfg['office_height'], 56 + $rows * 54 + 20);
    }

    public function spineY(): int
    {
        return $this->cfg['margin'] + $this->officeHeight() + intdiv($this->cfg['corridor_height'], 2);
    }

    /** @return array{x: int, y: int, w: int, h: int} */
    public function officeRect(): array
    {
        return ['x' => $this->cfg['margin'], 'y' => $this->cfg['margin'], 'w' => $this->cfg['office_width'], 'h' => $this->officeHeight()];
    }

    /** @return array{x: int, y: int, w: int, h: int} */
    public function workshopRect(): array
    {
        return [
            'x' => $this->cfg['margin'] + $this->cfg['office_width'] + $this->cfg['zone_gap'],
            'y' => $this->cfg['margin'],
            'w' => $this->cfg['workshop_width'],
            'h' => $this->officeHeight(),
        ];
    }

    /**
     * Where someone working in the Workshop stands (the floor in front of
     * the benches). A stand point, not an object: no offset is added.
     *
     * @return array{x: int, y: int}
     */
    public function workshopPoint(): array
    {
        $w = $this->workshopRect();

        return ['x' => $w['x'] + intdiv($w['w'], 2), 'y' => $w['y'] + $w['h'] - 26];
    }

    /** Group whose name equals a free-text location (e.g. Manual Activity "WWD 02"), or null. */
    public function groupIdByName(?string $label): ?int
    {
        $needle = mb_strtolower(trim((string) $label));

        if ($needle === '') {
            return null;
        }

        return $this->groups->first(fn (Group $g) => mb_strtolower(trim($g->name)) === $needle)?->id;
    }

    /** @return array{x: int, y: int, w: int, h: int} */
    public function areaRect(int $areaId): array
    {
        return [
            'x' => $this->cfg['margin'] + $this->areaIndex[$areaId] * $this->cfg['area_width'],
            'y' => $this->productionTop(),
            'w' => $this->cfg['area_width'],
            'h' => $this->cfg['area_height'],
        ];
    }

    public function zoneKey(?int $areaId): string
    {
        return $this->hasArea($areaId) ? 'area:'.$areaId : 'office';
    }

    /**
     * Idle "base" spot for the nth PIC of an area; PICs without a (valid)
     * area wait in the Office lobby.
     *
     * @return array{x: int, y: int, zone: string}
     */
    public function basePoint(?int $areaId, int $index): array
    {
        $c = $this->cfg;

        if (! $this->hasArea($areaId)) {
            $office = $this->officeRect();
            $col = $index % 8;

            return [
                'x' => $office['x'] + 30 + $col * $c['base_dx'],
                'y' => $office['y'] + $office['h'] - 30 - intdiv($index, 8) * $c['base_dy'],
                'zone' => 'office',
            ];
        }

        $rect = $this->areaRect($areaId);

        return [
            'x' => $rect['x'] + $c['base_origin']['x'] + ($index % $c['base_cols']) * $c['base_dx'],
            'y' => $rect['y'] + $c['base_origin']['y'] + intdiv($index, $c['base_cols']) * $c['base_dy'],
            'zone' => 'area:'.$areaId,
        ];
    }

    /**
     * Load the given machines and place them on their Area's machine grid.
     * Placement is automatic and never overlaps: each machine prefers the
     * slot derived from its id (so it stays put between polls) and, if that
     * is taken by a lower id, takes the next free one.
     *
     * Call once with every machine id the snapshot needs.
     *
     * @param  list<int>  $ids
     */
    public function preloadMachines(array $ids): void
    {
        $ids = array_values(array_diff(array_unique($ids), array_keys($this->machines)));

        if ($ids === []) {
            return;
        }

        $found = Machine::query()->whereIn('id', $ids)->orderBy('id')->get(['id', 'machine_number', 'area'])->keyBy('id');
        $c = $this->cfg;
        $perLayer = $c['machine_cols'] * $c['machine_rows'];
        $taken = [];

        foreach ($ids as $id) {
            $machine = $found->get($id);
            $areaId = $machine ? $this->areas->firstWhere('name', $machine->area)?->id : null;

            if ($areaId === null) {
                $this->machines[$id] = null; // unknown machine, or its Area is not on the map

                continue;
            }

            // Home slot from the id; if taken, the next free slot of the grid (wrapping around).
            // Only when the whole grid is in use does a machine go to a staggered extra layer.
            $home = $id % $perLayer;
            $slot = null;
            for ($i = 0; $i < $perLayer; $i++) {
                $candidate = ($home + $i) % $perLayer;
                if (! isset($taken[$areaId][$candidate])) {
                    $slot = $candidate;
                    break;
                }
            }
            $slot ??= max($perLayer, max(array_keys($taken[$areaId])) + 1);
            $taken[$areaId][$slot] = true;

            $layer = intdiv($slot, $perLayer);
            $inLayer = $slot % $perLayer;
            $rect = $this->areaRect($areaId);

            $this->machines[$id] = [
                'id' => $id,
                'number' => $machine->machine_number,
                'area_id' => $areaId,
                'x' => $rect['x'] + $c['machine_origin']['x'] + ($inLayer % $c['machine_cols']) * $c['machine_dx'] + $layer * 40,
                'y' => min($rect['y'] + $rect['h'] - 14, $rect['y'] + $c['machine_origin']['y'] + intdiv($inLayer, $c['machine_cols']) * $c['machine_dy'] + $layer * 20),
            ];
        }
    }

    /** @return array{id: int, number: string, area_id: int, x: int, y: int}|null null when the machine or its Area is not on the map */
    public function machineObject(int $machineId): ?array
    {
        $this->preloadMachines([$machineId]);

        return $this->machines[$machineId] ?? null;
    }

    /** @return array{id: int, name: string, x: int, y: int, area_id: int}|null */
    public function groupObject(int $groupId): ?array
    {
        $group = $this->groups->firstWhere('id', $groupId);

        if (! $group) {
            return null;
        }

        $rect = $this->areaRect($group->area_id);
        $c = $this->cfg;
        $slot = $this->groupSlot[$group->id] ?? 0;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'x' => $rect['x'] + $c['group_origin']['x'] + ($slot % $c['group_cols']) * $c['group_dx'],
            'y' => $rect['y'] + $c['group_origin']['y'] + intdiv($slot, $c['group_cols']) * $c['group_dy'],
            'area_id' => $group->area_id,
        ];
    }

    /** @return array{x: int, y: int}|null */
    public function oilStationObject(int $areaId): ?array
    {
        if (! $this->hasArea($areaId)) {
            return null;
        }

        $rect = $this->areaRect($areaId);

        return ['x' => $rect['x'] + $this->cfg['oil_station']['x'], 'y' => $rect['y'] + $this->cfg['oil_station']['y']];
    }

    /** @return array{x: int, y: int}|null */
    public function areaDefaultObject(int $areaId): ?array
    {
        if (! $this->hasArea($areaId)) {
            return null;
        }

        $rect = $this->areaRect($areaId);

        return ['x' => $rect['x'] + $this->cfg['default_anchor']['x'], 'y' => $rect['y'] + $this->cfg['default_anchor']['y']];
    }

    /**
     * The Area's patrol route as world points, rotated so that each PIC starts
     * at a different stop (by $seed, normally the user id) and walkers do not
     * march in step.
     *
     * @return list<array{x: int, y: int}>
     */
    public function patrolPoints(int $areaId, int $seed): array
    {
        $rect = $this->areaRect($areaId);
        $points = array_map(
            fn (array $p) => ['x' => $rect['x'] + $p[0], 'y' => $rect['y'] + $p[1]],
            $this->cfg['patrol'],
        );

        // x3: consecutive user ids start on well-separated stops (3 is coprime with the route length).
        $shift = ($seed * 3) % count($points);

        return array_values(array_merge(array_slice($points, $shift), array_slice($points, 0, $shift)));
    }

    /**
     * Desk of the nth AI character in the Office (rows of four along the back wall). The character stands at
     * this point; the desk is drawn just behind it.
     *
     * @return array{x: int, y: int, zone: string}
     */
    public function aiDeskPoint(int $index): array
    {
        $office = $this->officeRect();

        $col = $index % self::DESK_COLUMNS;
        $row = intdiv($index, self::DESK_COLUMNS);

        return ['x' => $office['x'] + 50 + $col * 85, 'y' => $office['y'] + 64 + $row * 54, 'zone' => 'office'];
    }

    /** Where a character stands when working at an object. */
    public function standPoint(array $object): array
    {
        return [
            'x' => $object['x'] + $this->cfg['stand_offset']['x'],
            'y' => $object['y'] + $this->cfg['stand_offset']['y'],
        ];
    }

    /**
     * Full map description for the client / admin editor.
     *
     * Static environment only (rooms, area columns, corridor, waypoints). Machines,
     * group markers and Oil Audit stations are drawn dynamically from the
     * snapshot, only while a PIC is using them.
     */
    public function toArray(): array
    {
        $office = $this->officeRect();
        $workshop = $this->workshopRect();
        $spineY = $this->spineY();

        $zones = [
            ['key' => 'office', 'type' => 'office', 'label' => 'Office', 'area_id' => null] + $office,
            ['key' => 'workshop', 'type' => 'workshop', 'label' => 'Workshop', 'area_id' => null] + $workshop,
        ];
        $waypoints = [
            ['id' => 'entry:office', 'x' => $office['x'] + intdiv($office['w'], 2), 'y' => $office['y'] + $office['h'] - 6, 'zone' => 'office'],
            ['id' => 'spine:office', 'x' => $office['x'] + intdiv($office['w'], 2), 'y' => $spineY, 'zone' => null],
            ['id' => 'entry:workshop', 'x' => $workshop['x'] + intdiv($workshop['w'], 2), 'y' => $workshop['y'] + $workshop['h'] - 6, 'zone' => 'workshop'],
            ['id' => 'spine:workshop', 'x' => $workshop['x'] + intdiv($workshop['w'], 2), 'y' => $spineY, 'zone' => null],
        ];
        $edges = [['entry:office', 'spine:office'], ['entry:workshop', 'spine:workshop']];
        $spine = [['spine:office', $waypoints[1]['x']], ['spine:workshop', $waypoints[3]['x']]];

        foreach ($this->areas as $area) {
            $rect = $this->areaRect($area->id);
            $key = 'area:'.$area->id;
            $cx = $rect['x'] + intdiv($rect['w'], 2);

            $zones[] = ['key' => $key, 'type' => 'production', 'label' => $area->name, 'area_id' => $area->id] + $rect;
            $waypoints[] = ['id' => 'entry:'.$key, 'x' => $cx, 'y' => $rect['y'] + 6, 'zone' => $key];
            $waypoints[] = ['id' => 'spine:'.$key, 'x' => $cx, 'y' => $spineY, 'zone' => null];
            $edges[] = ['entry:'.$key, 'spine:'.$key];
            $spine[] = ['spine:'.$key, $cx];
        }

        usort($spine, fn ($a, $b) => $a[1] <=> $b[1]);
        for ($i = 1; $i < count($spine); $i++) {
            $edges[] = [$spine[$i - 1][0], $spine[$i][0]];
        }

        return [
            'world' => ['width' => $this->width, 'height' => $this->height, 'spine_y' => $spineY, 'stand_offset' => $this->cfg['stand_offset']],
            'zones' => $zones,
            'waypoints' => $waypoints,
            'edges' => $edges,
        ];
    }
}
