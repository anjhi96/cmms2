<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ganti Filter Panel: a machine whose blower filter was found dead during
 * the schedule. OPEN until an action is recorded, then COMPLETED.
 */
class SmallPmFinding extends Model
{
    public const STATUSES = ['OPEN', 'COMPLETED'];

    protected $fillable = [
        'small_pm_schedule_id',
        'machine_id',
        'status',
        'action',
        'action_date',
    ];

    protected function casts(): array
    {
        return [
            'small_pm_schedule_id' => 'integer',
            'machine_id' => 'integer',
            'action_date' => 'date',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SmallPmSchedule::class, 'small_pm_schedule_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
