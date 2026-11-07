<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Small PM row: a set of machines cleaned in one go. Works like Group
 * (machines.small_pm_row_id, one row per machine) and is shared by every
 * Small PM tab.
 */
class SmallPmRow extends Model
{
    public const TASK_CLEANING_MOTOR = 'cleaning_motor';

    public const TASK_FILTER_PANEL = 'filter_panel';

    /** task_type => label */
    public const TASK_TYPES = [
        self::TASK_CLEANING_MOTOR => 'Cleaning Kisi-Kisi Motor',
        self::TASK_FILTER_PANEL => 'Ganti Filter Panel',
    ];

    protected $fillable = [
        'name',
        'area_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'area_id' => 'integer'];
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SmallPmSchedule::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
