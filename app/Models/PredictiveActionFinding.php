<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveActionFinding extends Model
{
    protected $fillable = ['problem_id', 'finding_option_id', 'finding'];

    public function problem(): BelongsTo
    {
        return $this->belongsTo(PredictiveActionProblem::class, 'problem_id');
    }
}
