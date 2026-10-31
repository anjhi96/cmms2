<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiForecastEvaluation extends Model
{
    protected $table = 'ai_forecast_evaluations';

    protected $guarded = [];

    public const AVAILABLE = 'AVAILABLE';

    public const NOT_AVAILABLE = 'NOT_AVAILABLE';

    protected function casts(): array
    {
        return [
            'notes' => 'array',
            'mae' => 'float',
            'wape' => 'float',
            'bias' => 'float',
            'train_from' => 'date',
            'train_to' => 'date',
            'test_from' => 'date',
            'test_to' => 'date',
        ];
    }
}
