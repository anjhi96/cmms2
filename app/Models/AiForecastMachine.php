<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiForecastMachine extends Model
{
    protected $table = 'ai_forecast_machines';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['first_pm_date' => 'date', 'last_pm_date' => 'date'];
    }
}
