<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineProblem extends Model
{
    protected $fillable = [
        'machine_type',
        'category',
        'problem',

    ];

    // Semua finding yang dimiliki category ini
    public function findings()
    {
        return $this->hasMany(
            MachineProblemFinding::class,
            'category',
            'category'
        );
    }

    // Semua histori PM yang memakai problem ini
    public function pmProblems()
    {
        return $this->hasMany(
            PMProblem::class,
            'machine_problem_id'
        );
    }
}
