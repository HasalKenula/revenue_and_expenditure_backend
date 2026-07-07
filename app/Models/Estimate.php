<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $table = 'estimates';
    
    protected $fillable = [
        'head',
        'program',
        'project',
        'sub_project',
        'object',
        'revenue_code_name',
        'estimate',
        're_estimate'
    ];
    
    protected $casts = [
        'head' => 'integer',
        'program' => 'integer',
        'project' => 'integer',
        'sub_project' => 'integer',
        'object' => 'integer',
        'estimate' => 'decimal:2',
        're_estimate' => 'decimal:2',
    ];
    
    /**
     * Get summary by head
     */
    public static function getSummaryByHead()
    {
        return self::selectRaw('head, COUNT(*) as count, SUM(estimate) as total_estimate, SUM(re_estimate) as total_re_estimate')
            ->groupBy('head')
            ->orderBy('head')
            ->get();
    }
    
    /**
     * Get summary by program
     */
    public static function getSummaryByProgram()
    {
        return self::selectRaw('program, COUNT(*) as count, SUM(estimate) as total_estimate, SUM(re_estimate) as total_re_estimate')
            ->groupBy('program')
            ->orderBy('program')
            ->get();
    }
}