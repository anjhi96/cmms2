<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sparepart extends Model
{
    protected $fillable = [
        'material_number',
        'location',
        'description',
        'remarks',
        'stock',
        'unit',
        'rop',
        'mrp_type',
        'price',
        'status',
        'machine_type',
        'segment',
        'pdt',
    ];

    public function pmSchedule()
    {
        return $this->belongsTo(PMSchedule::class);
    }

    public function pmSpareparts()
    {
        return $this->hasMany(PMSparepart::class);
    }
}
