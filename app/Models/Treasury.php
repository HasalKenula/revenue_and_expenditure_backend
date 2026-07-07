<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treasury extends Model
{
    // Constants for DR/CR
    const DR = 'DR';
    const CR = 'CR';
    
    protected $table = 'treasury';
    
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
        'xe'
    ];
    
    protected $casts = [
        'trno' => 'integer',
        'month' => 'integer',
        'dr_cr_code' => 'integer',
        'head' => 'integer',
        'program' => 'integer',
        'project' => 'integer',
        'sub_project' => 'integer',
        'object' => 'integer',
        'item' => 'integer',
        'funding' => 'integer',
        'head_no' => 'integer',
        'year' => 'integer',
        'cash_xe' => 'decimal:2',
        'cash' => 'decimal:2',
        'xe' => 'decimal:2',
    ];
    
    protected $attributes = [
        'subject' => 'S',
        'trno' => 400,
        'item' => 0,
        'funding' => 11,
        'head_no' => 400,
        'year' => 26,
        'cash_xe' => 0,
        'cash' => 0,
        'xe' => 0,
    ];
    
    /**
     * Get month name
     */
    public function getMonthNameAttribute()
    {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        return $months[$this->month] ?? 'Unknown';
    }
    
    /**
     * Get DR/CR options for dropdown
     */
    public static function getDrCrOptions()
    {
        return [
            self::DR => 'DR',
            self::CR => 'CR',
        ];
    }
    
    /**
     * Get summary by head
     */
    public static function getSummaryByHead()
    {
        return self::selectRaw('head, COUNT(*) as count, SUM(cash) as total_cash, SUM(xe) as total_xe, SUM(cash_xe) as total_cash_xe')
            ->groupBy('head')
            ->orderBy('head')
            ->get();
    }
    
    /**
     * Get summary by month
     */
    public static function getSummaryByMonth()
    {
        return self::selectRaw('month, COUNT(*) as count, SUM(cash) as total_cash, SUM(xe) as total_xe, SUM(cash_xe) as total_cash_xe')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }
    
    /**
     * Get summary by year
     */
    public static function getSummaryByYear()
    {
        return self::selectRaw('year, COUNT(*) as count, SUM(cash) as total_cash, SUM(xe) as total_xe, SUM(cash_xe) as total_cash_xe')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get();
    }
}