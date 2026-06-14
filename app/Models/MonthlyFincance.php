<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyFincance extends Model
{
    protected $table = 'monthly_fincances';
    
    protected $fillable = [
    'subject',
    'trno',
    'month',
    'sn',
    'dr_cr_code',
    'head',
    'program',
    'project',
    'sub_project',
    'object',
    'item',
    'funding',
    'dr_cr',
    'cash_xe',
    'head_no',
    'year',
    'cash',
    'xe',
    ];
    
   
}