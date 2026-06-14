<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalance extends Model
{
    //
     protected $table = 'opening_balances';
    
    protected $fillable = [
        'head',
        'year',
        'opening_balance'
    ];
    
    protected $casts = [
        'head' => 'integer',
        'year' => 'integer',
        'opening_balance' => 'decimal:2',
    ];
}
