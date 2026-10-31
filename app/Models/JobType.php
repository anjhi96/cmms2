<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobType extends Model
{
    public const KEY_PM = 'pm';

    public const KEY_OIL_AUDIT = 'oil_audit';

    public const KEY_OIL_AUDIT_ACTION = 'oil_audit_action';

    public const KEY_GREASING = 'greasing';

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
