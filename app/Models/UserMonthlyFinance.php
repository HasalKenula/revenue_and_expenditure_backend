<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMonthlyFinance extends Model
{
    protected $table = 'user_monthly_finances';
    
    protected $fillable = [
        'user_id',
        'username', // Add this
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
        'is_approved',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // Remove the relationship methods if User model doesn't exist or if you don't need them
    // Or keep them if User model exists
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}