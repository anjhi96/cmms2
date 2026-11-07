<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredictiveAction extends Model
{
    protected $fillable = [
        'work_order_id',
        'action_number',
        'remarks',
        'pic_user_id',
        'pic_name',
        'actioned_at',
    ];

    protected function casts(): array
    {
        return [
            'action_number' => 'integer',
            'actioned_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(PredictiveWorkOrder::class, 'work_order_id');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(PredictiveActionProblem::class, 'action_id')->orderBy('id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }
}
