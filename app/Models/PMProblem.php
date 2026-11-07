<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PMProblem extends Model
{
    protected $table = 'pm_problems';

    protected $fillable = [
        'pm_schedule_id',
        'machine_problem_id',
        'machine_problem_finding_id',
        'severity',
    ];

    public function pmSchedule()
    {
        return $this->belongsTo(PMSchedule::class);
    }

    public function machineProblem()
    {
        return $this->belongsTo(MachineProblem::class);
    }

    public function machineProblemFinding()
    {
        return $this->belongsTo(
            MachineProblemFinding::class,
            'machine_problem_finding_id'
        );
    }
}
