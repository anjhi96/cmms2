<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Master data for the operational areas (WWD, BUL, and any area added later
 * via Area Management) — the single source of truth every role/area/machine/
 * PM-schedule scoping rule reads from, replacing the hardcoded ['WWD','BUL']
 * lists that used to be duplicated across ~6 controllers.
 *
 * `name` is treated as immutable once created: `machines.area`,
 * `pm_schedules.area`, and `oil_audits.area` store this value as a plain
 * string (see Machine/PMSchedule/OilAudit::areaMaster()), so renaming it here
 * would silently orphan all existing rows referencing the old name. Area
 * Management only ever toggles `is_active`.
 */
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Area $area) {
            if (blank($area->slug)) {
                $area->slug = Str::slug($area->name);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }
}
