<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveFindingOption extends Model
{
    protected $fillable = ['problem_option_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(PredictiveProblemOption::class, 'problem_option_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isUsed(): bool
    {
        return PredictiveActionFinding::where('finding_option_id', $this->id)->exists();
    }
}
