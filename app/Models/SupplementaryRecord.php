<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplementaryRecord extends Model
{
    //
    protected $table = 'supplementary_records';
    
    protected $fillable = [
        'order_number',
        'year',
        'month',
        'head',
        'program',
        'project',
        'subproject',
        'object',
        'subobject',
        'fr66p',
        'fr66m',
        'supplementary_amount'
    ];
    
    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'head' => 'integer',
        'program' => 'integer',
        'project' => 'integer',
        'subproject' => 'integer',
        'object' => 'integer',
        'subobject' => 'integer',
        'fr66p' => 'decimal:2',
        'fr66m' => 'decimal:2',
        'supplementary_amount' => 'decimal:2',
    ];
}
