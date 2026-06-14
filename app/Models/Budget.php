<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $table = 'budgets';
    
    protected $fillable = [
        'head',
        'program',
        'project',
        'subproj',
        'object',
        'obj_detail',
        'funding',
        'objname',
        'amount',
    ];
    
    protected $casts = [
        'head' => 'integer',
        'program' => 'integer',
        'project' => 'integer',
        'subproj' => 'integer',
        'object' => 'integer',
        'funding' => 'integer',
        'amount' => 'decimal:2',
    ];
}