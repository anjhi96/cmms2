<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sparepart Planning configuration: a sparepart that is mandatory for a
 * machine type, with the quantity needed per PM. This is configuration only
 * — it is never evidence of actual usage (that lives in pm_spareparts).
 */
class MandatorySparepart extends Model
{
    protected $table = 'mandatory_spareparts';

    protected $fillable = [
        'machine_type',
        'sparepart_id',
        'qty',
    ];

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }
}
