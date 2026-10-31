<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiForecastResult extends Model
{
    protected $table = 'ai_forecast_results';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'basis' => 'array',
            'probability' => 'float',
            'avg_qty' => 'float',
            'expected_qty' => 'float',
            'mean_interval_days' => 'float',
            'last_used_date' => 'date',
        ];
    }
}
