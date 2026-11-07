<?php

namespace App\Models;

use App\Services\WeeklyPlan\MachineFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A coordinator's Monday-to-Friday PM plan for one area, used to compose the
 * weekly email to production. Deliberately separate from pm_schedules: moving
 * a machine here never changes its plan_date or pic there.
 */
class WeeklyPmPlan extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SENT = 'SENT';

    /** Upper bound of the per-day auto-fill limit dropdown. */
    public const MAX_DAY_LIMIT = 6;

    /** Per-day auto-fill limit until the coordinator picks another. */
    public const DEFAULT_DAY_LIMIT = 4;

    protected $fillable = [
        'area',
        'week_start',
        'status',
        'day_limits',
        'day_types',
        'sent_snapshot',
        'sent_at',
        'applied_pics',
        'pics_applied_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'immutable_date',
            'day_limits' => 'array',
            'day_types' => 'array',
            'sent_snapshot' => 'array',
            'sent_at' => 'datetime',
            'applied_pics' => 'array',
            'pics_applied_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(WeeklyPmPlanItem::class)->orderBy('day')->orderBy('sort_order')->orderBy('id');
    }

    /** @return list<CarbonImmutable> Monday..Friday of this plan's week. */
    public function workDays(): array
    {
        return array_map(fn (int $i) => $this->week_start->addDays($i), range(0, 4));
    }

    /** How many machines auto-fill may place on a day (0..6, default 4). */
    public function dayLimit(string $date): int
    {
        return (int) ($this->day_limits[$date] ?? self::DEFAULT_DAY_LIMIT);
    }

    /**
     * Machine family mix for auto-fill on a day. Rows saved before families
     * were introduced ("NDE SW") are read as their family and merged.
     *
     * @return list<array{type: string, count: int}>
     */
    public function dayTypes(string $date): array
    {
        $merged = [];

        foreach ($this->day_types[$date] ?? [] as $row) {
            $family = MachineFamily::of($row['type'] ?? '');
            $merged[$family] = ($merged[$family] ?? 0) + (int) ($row['count'] ?? 0);
        }

        return array_map(fn ($type, $count) => ['type' => $type, 'count' => $count], array_keys($merged), $merged);
    }

    public function weekEnd(): CarbonImmutable
    {
        return $this->week_start->addDays(4);
    }
}
