<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineLastPmOverrideLog extends Model
{
    public const ACTION_CREATE = 'CREATE';

    public const ACTION_UPDATE = 'UPDATE';

    public const ACTION_RESET = 'RESET';

    protected $fillable = [
        'machine_id',
        'previous_override_date',
        'new_override_date',
        'previous_reason',
        'new_reason',
        'action',
        'performed_by',
    ];

    protected $casts = [
        'previous_override_date' => 'date',
        'new_override_date' => 'date',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
