<?php

namespace App\Traits;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

trait LogsUserActivity
{
    /**
     * Log user activity
     * 
     * @param string $action
     * @param User|null $user
     * @param mixed $additionalInfo
     */
    protected function logUserActivity($action, $user = null, $additionalInfo = null)
    {
        try {
            // --- FIX 1: Use passed $user, otherwise fallback to auth() ---
            if (!$user) {
                $user = auth()->user();
            }

            Log::info('logUserActivity called', [
                'action' => $action,
                'user' => $user ? $user->email : 'no user'
            ]);

            if (!$user) {
                Log::warning('Attempted to log activity without authenticated user');
                return null;
            }

            $userAgent = Request::header('User-Agent');
            $ipAddress = Request::ip();
            $deviceType = $this->getDeviceType($userAgent);
            $browser = $this->getBrowser($userAgent);
            $platform = $this->getPlatform($userAgent);

            Log::info('User data for log', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'action' => $action
            ]);

            // If LOGIN - Create a new login record
            if ($action === 'login') {
                Log::info('Processing LOGIN action');
                
                $this->closeActiveSessions($user->id);

                $logData = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'action' => 'login',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'device_type' => $deviceType,
                    'browser' => $browser,
                    'platform' => $platform,
                    'login_time' => now(),
                    'status' => 'active',
                    'additional_info' => $additionalInfo
                ];

                Log::info('Creating login record', $logData);

                $log = SystemLog::create($logData);

                Log::info('Login record created', [
                    'log_id' => $log->id,
                    'login_time' => $log->login_time
                ]);

                return $log;
            }

            // If LOGOUT - Update the active login record
            if ($action === 'logout') {
                Log::info('Processing LOGOUT action');
                
                // Find the most recent active login record for this user
                $activeLogin = SystemLog::where('user_id', $user->id)
                    ->where('action', 'login')
                    ->where('status', 'active')
                    ->latest('login_time')
                    ->first();

                Log::info('Active login found', [
                    'found' => $activeLogin ? 'yes' : 'no',
                    'login_id' => $activeLogin ? $activeLogin->id : null,
                    'login_time' => $activeLogin ? $activeLogin->login_time : null
                ]);

                if ($activeLogin) {
                    // Calculate session duration
                    // $duration = now()->diffInSeconds($activeLogin->login_time);
                    // NEW (Fixed)
                    $duration = abs(now()->diffInSeconds($activeLogin->login_time));
                    
                    // Update the login record with logout info
                    $activeLogin->update([
                        'logout_time' => now(),
                        'session_duration' => $duration,
                        'status' => 'ended'
                    ]);

                    Log::info('User logged out', [
                        'user_id' => $user->id,
                        'log_id' => $activeLogin->id,
                        'duration' => $duration,
                        'login_time' => $activeLogin->login_time,
                        'logout_time' => now()
                    ]);

                    return $activeLogin;
                } else {
                    // If no active login found, create a logout record (fallback)
                    Log::warning('Logout without active session', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);

                    $log = SystemLog::create([
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'user_role' => $user->role,
                        'action' => 'logout',
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                        'device_type' => $deviceType,
                        'browser' => $browser,
                        'platform' => $platform,
                        'logout_time' => now(),
                        'status' => 'ended',
                        'additional_info' => 'Logout without active session'
                    ]);

                    return $log;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error logging user activity: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Close all active sessions for a user
     */
    protected function closeActiveSessions($userId)
    {
        try {
            Log::info('Closing active sessions for user: ' . $userId);
            
            $activeSessions = SystemLog::where('user_id', $userId)
                ->where('action', 'login')
                ->where('status', 'active')
                ->get();

            Log::info('Active sessions found', ['count' => $activeSessions->count()]);

            foreach ($activeSessions as $session) {
                $duration = now()->diffInSeconds($session->login_time);
                $session->update([
                    'logout_time' => now(),
                    'session_duration' => $duration,
                    'status' => 'ended'
                ]);
                
                Log::info('Closed active session', [
                    'user_id' => $userId,
                    'session_id' => $session->id,
                    'duration' => $duration
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error closing active sessions: ' . $e->getMessage());
        }
    }

    /**
     * Log failed login attempt
     */
    protected function logFailedLogin($email, $ip = null)
    {
        try {
            $userAgent = Request::header('User-Agent');
            
            SystemLog::create([
                'user_id' => null,
                'user_name' => 'Unknown',
                'user_email' => $email ?? 'unknown@unknown.com',
                'user_role' => 'guest',
                'action' => 'failed_login',
                'ip_address' => $ip ?? Request::ip(),
                'user_agent' => $userAgent,
                'device_type' => $this->getDeviceType($userAgent),
                'browser' => $this->getBrowser($userAgent),
                'platform' => $this->getPlatform($userAgent),
                'status' => 'failed',
                'additional_info' => 'Failed login attempt for email: ' . ($email ?? 'unknown')
            ]);

            Log::info('Failed login logged', ['email' => $email, 'ip' => $ip]);
        } catch (\Exception $e) {
            Log::error('Error logging failed login: ' . $e->getMessage());
        }
    }

    /**
     * Get device type from user agent
     */
    protected function getDeviceType($userAgent)
    {
        if (!$userAgent) return 'Unknown';
        
        if (str_contains($userAgent, 'Mobile')) return 'Mobile';
        if (str_contains($userAgent, 'Tablet')) return 'Tablet';
        return 'Desktop';
    }

    /**
     * Get browser from user agent
     */
    protected function getBrowser($userAgent)
    {
        if (!$userAgent) return 'Unknown';
        
        if (str_contains($userAgent, 'Chrome')) return 'Chrome';
        if (str_contains($userAgent, 'Firefox')) return 'Firefox';
        if (str_contains($userAgent, 'Safari')) return 'Safari';
        if (str_contains($userAgent, 'Edge')) return 'Edge';
        if (str_contains($userAgent, 'Opera')) return 'Opera';
        return 'Other';
    }

    /**
     * Get platform from user agent
     */
    protected function getPlatform($userAgent)
    {
        if (!$userAgent) return 'Unknown';
        
        if (str_contains($userAgent, 'Windows')) return 'Windows';
        if (str_contains($userAgent, 'Mac OS')) return 'Mac OS';
        if (str_contains($userAgent, 'Linux')) return 'Linux';
        if (str_contains($userAgent, 'Android')) return 'Android';
        if (str_contains($userAgent, 'iOS')) return 'iOS';
        return 'Other';
    }
}