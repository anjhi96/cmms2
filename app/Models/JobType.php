<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobType extends Model
{
    public const KEY_PM = 'pm';

    public const KEY_OIL_AUDIT = 'oil_audit';

    public const KEY_OIL_AUDIT_ACTION = 'oil_audit_action';

    public const KEY_PREDICTIVE = 'predictive';

    public const KEY_GREASING = 'greasing';

    /** Seeded by 2026_11_01_100000_add_vme_job_type_aliases; used by Small PM. */
    public const KEY_CLEANING = 'cleaning';

    public const KEY_MANUAL = 'manual';

    protected $fillable = ['key', 'name', 'animation_key', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(JobTypeAlias::class);
    }

    /** Lowercase, trimmed, single-spaced — the form aliases are stored and matched in. */
    public static function normalize(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($name)));
    }
}
