<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredictiveProblemOption extends Model
{
    protected $fillable = ['category', 'area', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function findings(): HasMany
    {
        return $this->hasMany(PredictiveFindingOption::class, 'problem_option_id')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForScope(Builder $query, string $category, string $area): Builder
    {
        return $query->where('category', $category)->where('area', $area);
    }

    /** Used by any saved Action, or by one of its Findings. Used options can only be deactivated. */
    public function isUsed(): bool
    {
        return PredictiveActionProblem::where('problem_option_id', $this->id)->exists()
            || PredictiveActionFinding::whereIn('finding_option_id', $this->findings()->select('id')->toBase())->exists();
    }

    /** Case and surrounding-space insensitive form used for duplicate checks. */
    public static function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
