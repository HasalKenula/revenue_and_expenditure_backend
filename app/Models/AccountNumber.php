<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountNumber extends Model
{
    use HasFactory;

    protected $table = 'account_numbers';

    protected $fillable = [
        'account_number',
        'description',
    ];

    public function revenueAccountData()
    {
        return $this->hasMany(RevenueAccountData::class, 'account_number_id');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('account_number', 'LIKE', "%{$search}%")
                     ->orWhere('description', 'LIKE', "%{$search}%");
    }
}