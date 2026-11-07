<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PMChecklist extends Model
{
    protected $table = 'pm_checklists';

    protected $fillable = [

        'pm_schedule_id',

        'machine_checklist_id',

        'clean',

        'lubrication',

        'replace',

        'check',

        'remarks',

    ];

    public function pmSchedule()
    {
        return $this->belongsTo(PMSchedule::class);
    }

    public function machineChecklist()
    {
        return $this->belongsTo(MachineChecklist::class);
    }

    public function getResultsAttribute(): array
    {
        $results = [];

        if ($this->check === 'YES') {
            $results[] = [
                'text' => 'CHECK',
                'badge' => 'bg-blue-100 text-blue-700',
            ];
        }

        if ($this->clean === 'YES') {
            $results[] = [
                'text' => 'CLEAN',
                'badge' => 'bg-emerald-100 text-emerald-700',
            ];
        }

        if ($this->lubrication === 'YES') {
            $results[] = [
                'text' => 'LUBRICATION',
                'badge' => 'bg-yellow-100 text-yellow-700',
            ];
        }

        if ($this->replace === 'YES') {
            $results[] = [
                'text' => 'REPLACE',
                'badge' => 'bg-red-100 text-red-700',
            ];
        }

        return $results;
    }
}
