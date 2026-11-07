<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineChecklist extends Model
{
    protected $fillable = [
        'machine_type',
        'section',
        'checklist_item',
        'section_order',
        'item_order',
        'maintenance_type',

    ];
}
