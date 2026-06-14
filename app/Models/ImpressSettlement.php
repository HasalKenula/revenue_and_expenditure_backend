<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpressSettlement extends Model
{
    //
     protected $table = 'impress_settlements';
    
    protected $fillable = [
        'head',
        'year',
        'month',
        'amount'
    ];
    
    protected $casts = [
        'head' => 'integer',
        'year' => 'integer',
        'month' => 'integer',
        'amount' => 'decimal:2',
    ];
    
    // Get month name
    public function getMonthNameAttribute()
    {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        return $months[$this->month] ?? 'Unknown';
    }
}
