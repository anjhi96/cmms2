<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Display-only Last PM for Machine History. Never read by PM scheduling,
 * Next PM, overdue, dashboard, forecasting or report code.
 */
class MachineLastPmOverride extends Model
{
    protected $fillable = [
        'machine_id',
        'override_date',
        'reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'override_date' => 'date',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
