<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One stored output of an AI character (VME-07). Written only by App\Services\Vme\Ai. */
class AiCharacterRun extends Model
{
    protected $fillable = [
        'character_key', 'status', 'summary', 'input', 'result', 'result_hash', 'source',
        'source_ref_type', 'source_ref_id', 'started_at', 'finished_at', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }
}
