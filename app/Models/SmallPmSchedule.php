<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Small PM job on one row, scheduled the Greasing way: plan_date,
 * due_date = plan_date + 14, status from action_date vs due_date. The PIC is
 * kept by user id; an unassigned schedule may be taken by any PIC of the
 * row's area.
 */
class SmallPmSchedule extends Model
{
    public const STATUSES = ['OPEN', 'FINISH', 'FINISH ON TIME'];

    public const DONE_STATUSES = ['FINISH', 'FINISH ON TIME'];

    protected $fillable = [
        'small_pm_row_id',
        'task_type',
        'order_number',
        'cycle',
        'plan_date',
        'due_date',
        'pic_user_id',
        'action_date',
        'start_time',
        'status',
        'remarks',
        'generated_from_id',
    ];

    protected function casts(): array
    {
        return [
            'small_pm_row_id' => 'integer',
            'pic_user_id' => 'integer',
            'generated_from_id' => 'integer',
            'plan_date' => 'date',
            'due_date' => 'date',
            'action_date' => 'date',
            'start_time' => 'datetime',
        ];
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(SmallPmRow::class, 'small_pm_row_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function areaName(): ?string
    {
        return $this->row?->area?->name;
    }

    public function isDone(): bool
    {
        return in_array($this->status, self::DONE_STATUSES, true);
    }

    /** Started and not finished: what Today's Activity reads, same rule as Greasing. */
    public function scopeActiveActivity(Builder $query): Builder
    {
        return $query->whereNotNull('start_time')->whereNotIn('status', self::DONE_STATUSES);
    }

    /**
     * Admin / Supervisor: their areas. Koordinator: own area. PIC: own area,
     * and only schedules that are theirs or still unassigned.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if ($user->seesAllAreas()) {
            $restricted = $user->restrictedAreaNames();

            return $restricted === null
                ? $query
                : $query->whereHas('row.area', fn (Builder $q) => $q->whereIn('areas.name', $restricted));
        }

        if ((! $user->isKoordinator() && ! $user->isPic()) || ! $user->area_id) {
            return $query->whereRaw('0 = 1');
        }

        $query->whereHas('row', fn (Builder $q) => $q->where('area_id', $user->area_id));

        if ($user->isPic()) {
            $query->where(fn (Builder $q) => $q->whereNull('pic_user_id')->orWhere('pic_user_id', $user->id));
        }

        return $query;
    }

    /** Per-record counterpart of scopeVisibleToUser(). */
    public function isAccessibleBy(User $user): bool
    {
        $area = $this->areaName();

        if ($user->seesAllAreas()) {
            return $area === null || $user->hasArea($area);
        }

        if ((! $user->isKoordinator() && ! $user->isPic()) || ! $user->area_id) {
            return false;
        }

        $inArea = (int) $this->row?->area_id === (int) $user->area_id;

        return $user->isKoordinator()
            ? $inArea
            : $inArea && ($this->pic_user_id === null || $this->pic_user_id === (int) $user->id);
    }

    /** Same rule as Greasing::resolveStatus(). */
    public static function resolveStatus($actionDate, $dueDate): string
    {
        return Greasing::resolveStatus($actionDate, $dueDate);
    }

    public static function calculateDueDate($planDate): \Illuminate\Support\Carbon
    {
        return Greasing::calculateDueDate($planDate);
    }

    /** "26W" -> 26 */
    public static function cycleWeeks(string $cycle): int
    {
        return (int) rtrim(strtoupper($cycle), 'W');
    }
}
