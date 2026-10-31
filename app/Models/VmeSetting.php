<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VmeSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];
}
