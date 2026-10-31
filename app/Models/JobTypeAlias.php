<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobTypeAlias extends Model
{
    protected $fillable = ['job_type_id', 'alias'];

    public function jobType(): BelongsTo
    {
        return $this->belongsTo(JobType::class);
    }
}
