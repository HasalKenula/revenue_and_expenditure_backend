<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueOpeningBalance extends Model
{
    use HasFactory;

    protected $table = 'revenue_opening_balances';

    protected $fillable = [
        'account_number_id',
        'amount',
        'year',
        'estimate_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the account number that owns this opening balance.
     */
    public function accountNumber()
    {
        return $this->belongsTo(AccountNumber::class, 'account_number_id');
    }

    /**
     * Get the estimate that owns this opening balance.
     */
    public function estimate()
    {
        return $this->belongsTo(Estimate::class, 'estimate_id');
    }

    /**
     * Get the account number value directly
     */
    public function getAccountNumberAttribute()
    {
        return $this->accountNumber ? $this->accountNumber->account_number : null;
    }

    /**
     * Get the account description directly
     */
    public function getAccountDescriptionAttribute()
    {
        return $this->accountNumber ? $this->accountNumber->description : null;
    }

    /**
     * Get the account number with description
     */
    public function getAccountNumberWithDescriptionAttribute()
    {
        if ($this->accountNumber) {
            return $this->accountNumber->account_number . ' - ' . $this->accountNumber->description;
        }
        return null;
    }

    /**
     * Get revenue code name from the related estimate
     */
    public function getRevenueCodeNameAttribute()
    {
        return $this->estimate ? $this->estimate->revenue_code_name : null;
    }

    /**
     * Get the combined revenue code (head-project-object) from the related estimate
     */
    public function getRevenueCodeAttribute()
    {
        if (!$this->estimate) {
            return null;
        }
        
        $parts = [];
        if ($this->estimate->head) $parts[] = $this->estimate->head;
        if ($this->estimate->project) $parts[] = $this->estimate->project;
        if ($this->estimate->object) $parts[] = $this->estimate->object;
        
        return !empty($parts) ? implode('-', $parts) : null;
    }

    /**
     * Scope for searching
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('year', 'LIKE', "%{$search}%")
                     ->orWhere('amount', 'LIKE', "%{$search}%")
                     ->orWhereHas('accountNumber', function ($q) use ($search) {
                         $q->where('account_number', 'LIKE', "%{$search}%")
                           ->orWhere('description', 'LIKE', "%{$search}%");
                     })
                     ->orWhereHas('estimate', function ($q) use ($search) {
                         $q->where('revenue_code_name', 'LIKE', "%{$search}%")
                           ->orWhere('head', 'LIKE', "%{$search}%")
                           ->orWhere('project', 'LIKE', "%{$search}%")
                           ->orWhere('object', 'LIKE', "%{$search}%");
                     });
    }

    /**
     * Scope filter by account number
     */
    public function scopeByAccountNumber($query, $accountNumberId)
    {
        return $query->where('account_number_id', $accountNumberId);
    }

    /**
     * Scope filter by year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope filter by amount range
     */
    public function scopeAmountBetween($query, $min, $max)
    {
        return $query->whereBetween('amount', [$min, $max]);
    }

    /**
     * Scope filter by estimate
     */
    public function scopeByEstimate($query, $estimateId)
    {
        return $query->where('estimate_id', $estimateId);
    }

    /**
     * Get years from existing records
     */
    public static function getAvailableYears()
    {
        return self::select('year')
            ->whereNotNull('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->values();
    }

    /**
     * Get summary by account number
     */
    public static function getSummary()
    {
        return self::with(['accountNumber', 'estimate'])
            ->selectRaw('account_number_id, COUNT(*) as total_records, SUM(amount) as total_amount')
            ->groupBy('account_number_id')
            ->get()
            ->map(function ($item) {
                return [
                    'account_number_id' => $item->account_number_id,
                    'account_number' => $item->accountNumber->account_number ?? null,
                    'account_description' => $item->accountNumber->description ?? null,
                    'total_records' => $item->total_records,
                    'total_amount' => $item->total_amount,
                ];
            });
    }
}
