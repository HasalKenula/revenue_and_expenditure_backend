<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeadInfo extends Model
{
    protected $table = 'head_info';
    
    protected $primaryKey = 'head';
    public $incrementing = false;
    protected $keyType = 'int';
    
    protected $fillable = [
        'head',
        'description'
    ];
    
    protected $casts = [
        'head' => 'integer',
    ];
    
    /**
     * Relationship with ImpressIssue
     */
    public function impressIssues()
    {
        return $this->hasMany(ImpressIssue::class, 'head', 'head');
    }
}