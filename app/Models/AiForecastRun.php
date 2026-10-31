<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiForecastRun extends Model
{
    public const RUNNING = 'RUNNING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    protected $table = 'ai_forecast_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'summary' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'history_from' => 'date',
            'history_to' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(AiForecastMachine::class, 'run_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AiForecastResult::class, 'run_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(AiForecastEvaluation::class, 'run_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(AiForecastRecommendation::class, 'run_id');
    }
}
