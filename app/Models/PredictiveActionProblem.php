<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredictiveActionProblem extends Model
{
    protected $fillable = ['action_id', 'problem_option_id', 'problem'];

    public function action(): BelongsTo
    {
        return $this->belongsTo(PredictiveAction::class, 'action_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(PredictiveActionFinding::class, 'problem_id')->orderBy('id');
    }
}
