<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemLogController extends Controller
{
    /**
     * Get all system logs with filters and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = SystemLog::with('user');

            // Apply filters
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('user_name', 'like', "%{$search}%")
                      ->orWhere('user_email', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $logs = $query->paginate($perPage);

            // Format the logs
            $formattedLogs = $logs->items();
            foreach ($formattedLogs as $log) {
                $log->formatted_duration = $this->formatDuration($log->session_duration);
                
                $log->login_time_formatted = $log->login_time ? $log->login_time->format('Y-m-d H:i:s') : null;
                $log->logout_time_formatted = $log->logout_time ? $log->logout_time->format('Y-m-d H:i:s') : null;
                $log->created_at_formatted = $log->created_at->format('Y-m-d H:i:s');
            }

            // Get summary statistics (Global, not filtered)
            $summary = [
                'total_logs' => SystemLog::count(),
                'total_logins' => SystemLog::where('action', 'login')->count(),
                'total_logouts' => SystemLog::where('action', 'logout')->count(),
                'active_sessions' => SystemLog::where('status', 'active')->count(),
                'today_logins' => SystemLog::where('action', 'login')->whereDate('created_at', today())->count(),
            ];

            // Recent activities (Global, not filtered)
            $recentActivities = SystemLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    $log->formatted_duration = $this->formatDuration($log->session_duration);
                    return $log;
                });

            return response()->json([
                'success' => true,
                'data' => $formattedLogs,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
                'summary' => $summary,
                'recent_activities' => $recentActivities
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching system logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching system logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single log entry
     */
    public function show($id)
    {
        try {
            $log = SystemLog::with('user')->find($id);
            
            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log entry not found'
                ], 404);
            }

            $log->formatted_duration = $this->formatDuration($log->session_duration);

            return response()->json([
                'success' => true,
                'data' => $log
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getStatistics()
    {
        try {
            $today = today();
            $weekAgo = now()->subDays(7);
            $monthAgo = now()->subDays(30);

            $statistics = [
                'today' => [
                    'logins' => SystemLog::where('action', 'login')->whereDate('created_at', $today)->count(),
                    'logouts' => SystemLog::where('action', 'logout')->whereDate('created_at', $today)->count(),
                    'unique_users' => SystemLog::whereDate('created_at', $today)->distinct('user_id')->count('user_id'),
                ],
                'week' => [
                    'logins' => SystemLog::where('action', 'login')->whereDate('created_at', '>=', $weekAgo)->count(),
                    'logouts' => SystemLog::where('action', 'logout')->whereDate('created_at', '>=', $weekAgo)->count(),
                    'unique_users' => SystemLog::whereDate('created_at', '>=', $weekAgo)->distinct('user_id')->count('user_id'),
                ],
                'month' => [
                    'logins' => SystemLog::where('action', 'login')->whereDate('created_at', '>=', $monthAgo)->count(),
                    'logouts' => SystemLog::where('action', 'logout')->whereDate('created_at', '>=', $monthAgo)->count(),
                    'unique_users' => SystemLog::whereDate('created_at', '>=', $monthAgo)->distinct('user_id')->count('user_id'),
                ],
                'active_sessions' => SystemLog::where('status', 'active')->count(),
                'total_actions' => SystemLog::count(),
                'average_session_duration' => SystemLog::whereNotNull('session_duration')->avg('session_duration') ?? 0,
            ];

            $loginByHour = SystemLog::where('action', 'login')
                ->select(DB::raw('HOUR(login_time) as hour'), DB::raw('COUNT(*) as count'))
                ->whereNotNull('login_time')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            $statistics['login_by_hour'] = $loginByHour;

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        try {
            $users = User::select('id', 'name', 'email')
                ->orderBy('name')
                ->get();

            $actions = ['login', 'logout', 'failed_login'];
            $statuses = ['active', 'ended', 'expired', 'failed'];

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'actions' => $actions,
                    'statuses' => $statuses,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export logs to CSV
     */
    public function export(Request $request)
    {
        try {
            $query = SystemLog::with('user');

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $logs = $query->orderBy('created_at', 'desc')->get();

            $headers = [
                'User Name', 'User Email', 'User Role', 'Action', 'IP Address', 'Device Type', 
                'Browser', 'Platform', 'Login Time', 'Logout Time', 'Session Duration', 'Status', 'Created At'
            ];

            $csvData = [];
            $csvData[] = implode(',', $headers);

            foreach ($logs as $log) {
                $row = [
                    '"' . ($log->user_name ?? '') . '"',
                    '"' . ($log->user_email ?? '') . '"',
                    '"' . ($log->user_role ?? '') . '"',
                    '"' . ($log->action ?? '') . '"',
                    '"' . ($log->ip_address ?? '-') . '"',
                    '"' . ($log->device_type ?? '-') . '"',
                    '"' . ($log->browser ?? '-') . '"',
                    '"' . ($log->platform ?? '-') . '"',
                    '"' . ($log->login_time ? $log->login_time->format('Y-m-d H:i:s') : '-') . '"',
                    '"' . ($log->logout_time ? $log->logout_time->format('Y-m-d H:i:s') : '-') . '"',
                    '"' . $this->formatDuration($log->session_duration) . '"',
                    '"' . ($log->status ?? '') . '"',
                    '"' . ($log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '') . '"'
                ];
                $csvData[] = implode(',', $row);
            }

            $csvContent = implode("\n", $csvData);
            $filename = 'system_logs_' . date('Y-m-d_His') . '.csv';

            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename={$filename}");

        } catch (\Exception $e) {
            Log::error('Error exporting logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format duration in seconds to human readable format
     */
    private function formatDuration($seconds)
    {
        if (!$seconds && $seconds !== 0) return '-';
        
        $seconds = abs((int) $seconds); // ABS() is crucial to fix negative values
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf("%02d hrs %02d min %02d sec", $hours, $minutes, $secs);
        }
        if ($minutes > 0) {
            return sprintf("%02d min %02d sec", $minutes, $secs);
        }
        return sprintf("%02d sec", $secs);
    }
}