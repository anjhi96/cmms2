<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * `area` remains a plain, master-data-validated string column (see
 * MachineController/MachinesImport, which validate it against
 * Area::active()) rather than a real FK — this table is large, heavily
 * indexed, and queried by string equality throughout the app; a soft
 * `areaMaster()` relation below gives read access to the master row without
 * touching that existing column or its indexes.
 */

class Machine extends Model
{
    protected $fillable = [
        'machine_number',
        'group_id',
        'area',
        'machine_type',
        'description',
        'status',
        'install_date',
        'criticality',
        'remarks',
        'pm_cycle_value',
        'pm_cycle_unit',
    ];

    protected $casts = [
        'pm_cycle_value' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function oilAudits(): HasMany
    {
        return $this->hasMany(OilAudit::class);
    }

    public function pmSchedules(): HasMany
    {
        return $this->hasMany(PMSchedule::class);
    }

    public function latestOilAudit(): HasOne
    {
        return $this->hasOne(OilAudit::class)->latestOfMany('audited_at');
    }

    /**
     * Read/UI convenience relation onto the Area master row matching this
     * machine's `area` string. Deliberately NOT named area() — `area` is
     * already a real column on this model, and Eloquent always resolves
     * $model->area to that column, never to a same-named relation method.
     */
    public function areaMaster(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area', 'name');
    }
}
