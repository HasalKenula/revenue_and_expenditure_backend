<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueReceipt extends Model
{
    use HasFactory;

    protected $table = 'revenue_receipts';

    protected $fillable = [
        'account_number_id',
        'amount',
        'month',
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
     * Get the account number that owns this revenue receipt.
     */
    public function accountNumber()
    {
        return $this->belongsTo(AccountNumber::class, 'account_number_id');
    }

    /**
     * Get the estimate that owns this revenue receipt.
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
        return $query->where('month', 'LIKE', "%{$search}%")
                     ->orWhere('year', 'LIKE', "%{$search}%")
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
     * Scope filter by month
     */
    public function scopeByMonth($query, $month)
    {
        return $query->where('month', 'LIKE', "%{$month}%");
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
     * Get month options for dropdown
     */
    public static function getMonthOptions()
    {
        return [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
        ];
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
     * Get unique months from existing records
     */
    public static function getAvailableMonths()
    {
        return self::select('month')
            ->whereNotNull('month')
            ->distinct()
            ->orderBy('month', 'asc')
            ->pluck('month');
    }
}