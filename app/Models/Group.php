<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'name',
        'area_id',
    ];

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function greasings(): HasMany
    {
        return $this->hasMany(Greasing::class);
    }

    /**
     * A real FK, backfilled once (see the
     * 2026_09_29_090400_add_area_id_to_groups_table migration) from the same
     * name-matching rule the old Group::inferredArea() used. Groups whose
     * name matched neither WWD nor BUL stay area_id = null — Greasing's
     * "exclude the opposite area" visibility rule depends on that null being
     * preserved rather than defaulted.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
