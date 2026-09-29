<?php

// app/Models/SystemLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'action',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'login_time',
        'logout_time',
        'session_duration',
        'status',
        'additional_info'
    ];

    protected $casts = [
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
        'session_duration' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['formatted_duration'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted session duration
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->session_duration) return '-';
        
        $seconds = $this->session_duration;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
        }
        if ($minutes > 0) {
            return sprintf("%02d:%02d", $minutes, $secs);
        }
        return sprintf("%02d seconds", $secs);
    }

    /**
     * Check if session is active
     */
    public function getIsActiveAttribute()
    {
        return $this->status === 'active' && $this->logout_time === null;
    }

    // Scope for filtering
    public function scopeFilter($query, $filters)
    {
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query;
    }
}