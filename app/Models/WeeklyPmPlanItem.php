<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyPmPlanItem extends Model
{
    protected $fillable = [
        'weekly_pm_plan_id',
        'pm_schedule_id',
        'machine_id',
        'machine_number',
        'machine_type',
        'day',
        'pic',
        'construction_note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            // MySQL hands foreign keys back as strings; the board matches on ids.
            'pm_schedule_id' => 'integer',
            'machine_id' => 'integer',
            'weekly_pm_plan_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPmPlan::class, 'weekly_pm_plan_id');
    }

    public function pmSchedule(): BelongsTo
    {
        return $this->belongsTo(PMSchedule::class);
    }

    /** The row shape used by the email composer and the sent snapshot. */
    public function toRow(): array
    {
        return [
            'pm_schedule_id' => $this->pm_schedule_id,
            'machine_number' => $this->machine_number,
            'machine_type' => $this->machine_type,
            'day' => $this->day->toDateString(),
            'pic' => $this->pic,
            'construction_note' => $this->construction_note,
        ];
    }
}
