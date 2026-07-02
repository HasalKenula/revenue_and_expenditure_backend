<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemCode extends Model
{
    protected $table = 'item_code';
    
    protected $primaryKey = 'item';
    public $incrementing = false;
    protected $keyType = 'int';
    
    protected $fillable = [
        'item',
        'year',
        'description'
    ];
    
    protected $casts = [
        'item' => 'integer',
        'year' => 'integer',
    ];
    
    /**
     * Get the year range for dropdown
     */
    public static function getYearRange()
    {
        $currentYear = date('Y');
        $years = [];
        for ($i = $currentYear; $i >= $currentYear - 20; $i--) {
            $years[] = $i;
        }
        return $years;
    }
}