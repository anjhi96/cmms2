<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PMMeasurement extends Model
{
    protected $table = 'pm_measurements';

    protected $fillable = [
        'pm_schedule_id',
        'machine_measurement_id',
        'measurement_item',
        'standard',
        'measurement_value',
        'unit',
    ];

    public function pmSchedule()
    {
        return $this->belongsTo(PMSchedule::class);
    }
}
