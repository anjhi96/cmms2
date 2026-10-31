<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PMSparepart extends Model
{
    protected $table = 'pm_spareparts';

    protected $fillable = [
        'pm_schedule_id',
        'sparepart_id',
        'qty',
        'source',
        'injection_import_id',
    ];

    /** Marks usage injected from a warehouse issue (see SparepartInjectionService). */
    public const SOURCE_WAREHOUSE = 'WAREHOUSE';

    public function isFromWarehouse(): bool
    {
        return $this->source === self::SOURCE_WAREHOUSE;
    }

    public function pmSchedule()
    {
        return $this->belongsTo(PMSchedule::class);
    }

    public function sparepart()
    {
        return $this->belongsTo(Sparepart::class);
    }
}
