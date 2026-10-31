<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparepartInjectionImport extends Model
{
    protected $fillable = [
        'user_id', 'area', 'filename', 'total_rows', 'processed_rows', 'usage_added', 'skipped_rows', 'failed_rows',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SparepartInjectionRow::class, 'import_id');
    }

    /**
     * History visibility: ADMIN and an unrestricted SUPERVISOR see every
     * import; KOORDINATOR sees imports uploaded from their own area; a
     * restricted SUPERVISOR sees imports of their assigned areas. ADMIN
     * uploads (area NULL) are therefore not exposed to area-bound users.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isSupervisor()) {
            $restricted = $user->restrictedAreaNames();

            return $restricted === null ? $query : $query->whereIn('area', $restricted);
        }

        return $user->isKoordinator() && $user->area
            ? $query->where('area', $user->area->name)
            : $query->whereRaw('0 = 1');
    }
}
