<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveReopen extends Model
{
    protected $fillable = [
        'work_order_id',
        'user_id',
        'user_name',
        'reopened_at',
    ];

    protected function casts(): array
    {
        return [
            'reopened_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(PredictiveWorkOrder::class, 'work_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
