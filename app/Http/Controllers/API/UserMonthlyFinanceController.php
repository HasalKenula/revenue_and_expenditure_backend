<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserMonthlyFinance;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UserMonthlyFinanceImport;
use Illuminate\Support\Facades\Validator;

class UserMonthlyFinanceController extends Controller
{
    /**
     * Get user's own uploaded data
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $query = UserMonthlyFinance::where('user_id', $userId);
            
            // Filter by month
            if ($request->filled('month')) {
                $query->where('month', $request->month);
            }
            
            // Filter by year using created_at
            if ($request->filled('year')) {
                $query->whereYear('created_at', $request->year);
            }
            
            // Filter by approval status
            if ($request->filled('is_approved')) {
                $query->where('is_approved', $request->is_approved);
            }
            
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('id', 'asc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                    'last_page' => $records->lastPage(),
                ],
                'totals' => [
                    'total_cash' => $query->sum('cash'),
                    'total_xe' => $query->sum('xe'),
                    'total_cash_xe' => $query->sum('cash_xe'),
                    'total_records' => $query->count(),
                    'pending_count' => $query->where('is_approved', false)->count(),
                    'approved_count' => $query->where('is_approved', true)->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single record
     */
    public function show($id)
    {
        try {
            $record = UserMonthlyFinance::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Excel file for user
     */
    public function import(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:51200'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = $request->user();
            $userId = $user->id;
            $username = $user->name ?? $user->email ?? 'User';
            
            $import = new UserMonthlyFinanceImport($userId, $username);
            Excel::import($import, $request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => 'Data uploaded successfully. Waiting for approval.',
                'imported_count' => $import->getImportedCount(),
                'skipped_count' => $import->getSkippedCount()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users' data for expenditure manager - FILTER BY USERNAME
     */
    public function getAllUsersData(Request $request)
    {
        try {
            if ($request->user()->role !== 'expenditure_manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only expenditure managers can access this.'
                ], 403);
            }

            $query = UserMonthlyFinance::query();
            
            // Filter by username
            if ($request->filled('username')) {
                $query->where('username', 'LIKE', '%' . $request->username . '%');
            }
            
            if ($request->filled('is_approved')) {
                $query->where('is_approved', $request->is_approved);
            }
            if ($request->filled('month')) {
                $query->where('month', $request->month);
            }
            if ($request->filled('year')) {
                $query->whereYear('created_at', $request->year);
            }
            
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('id', 'asc')->paginate($perPage);
            
            // Get summary by username
            $userSummary = UserMonthlyFinance::selectRaw('user_id, username, COUNT(*) as total, 
                SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
                SUM(cash) as total_cash,
                SUM(xe) as total_xe,
                SUM(cash_xe) as total_cash_xe')
                ->groupBy('user_id', 'username')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                    'last_page' => $records->lastPage(),
                ],
                'user_summary' => $userSummary,
                'totals' => [
                    'total_records' => $query->count(),
                    'pending_count' => $query->where('is_approved', false)->count(),
                    'approved_count' => $query->where('is_approved', true)->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a single record and move to main table
     */
    public function approve(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'expenditure_manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only expenditure managers can approve.'
                ], 403);
            }

            $record = UserMonthlyFinance::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            if ($record->is_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'This record is already approved'
                ], 400);
            }

            $mainRecord = MonthlyFincance::create([
                'subject' => $record->subject,
                'trno' => $record->trno,
                'month' => $record->month,
                'sn' => $record->sn,
                'dr_cr_code' => $record->dr_cr_code,
                'head' => $record->head,
                'program' => $record->program,
                'project' => $record->project,
                'sub_project' => $record->sub_project,
                'object' => $record->object,
                'item' => $record->item,
                'funding' => $record->funding,
                'dr_cr' => $record->dr_cr,
                'cash_xe' => $record->cash_xe,
                'head_no' => $record->head_no,
                'year' => $record->year,
                'cash' => $record->cash,
                'xe' => $record->xe,
            ]);

            $record->update([
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Record approved and moved to main table successfully',
                'data' => [
                    'approved_record' => $record,
                    'moved_to_main' => $mainRecord
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve multiple records
     */
    public function approveMultiple(Request $request)
    {
        try {
            if ($request->user()->role !== 'expenditure_manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only expenditure managers can approve.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'exists:user_monthly_finances,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $approvedCount = 0;
            $failedIds = [];

            foreach ($request->ids as $id) {
                $record = UserMonthlyFinance::find($id);
                
                if ($record && !$record->is_approved) {
                    MonthlyFincance::create([
                        'subject' => $record->subject,
                        'trno' => $record->trno,
                        'month' => $record->month,
                        'sn' => $record->sn,
                        'dr_cr_code' => $record->dr_cr_code,
                        'head' => $record->head,
                        'program' => $record->program,
                        'project' => $record->project,
                        'sub_project' => $record->sub_project,
                        'object' => $record->object,
                        'item' => $record->item,
                        'funding' => $record->funding,
                        'dr_cr' => $record->dr_cr,
                        'cash_xe' => $record->cash_xe,
                        'head_no' => $record->head_no,
                        'year' => $record->year,
                        'cash' => $record->cash,
                        'xe' => $record->xe,
                    ]);

                    $record->update([
                        'is_approved' => true,
                        'approved_at' => now(),
                        'approved_by' => $request->user()->id,
                    ]);
                    
                    $approvedCount++;
                } else {
                    $failedIds[] = $id;
                }
            }

            return response()->json([
                'success' => true,
                'message' => $approvedCount . ' record(s) approved successfully',
                'approved_count' => $approvedCount,
                'failed_ids' => $failedIds
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a single record (Expenditure Manager only)
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Only expenditure manager can delete
            if ($request->user()->role !== 'expenditure_manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only expenditure managers can delete records.'
                ], 403);
            }

            $record = UserMonthlyFinance::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            $record->delete();

            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple records (Expenditure Manager only)
     */
    public function destroyMultiple(Request $request)
    {
        try {
            // Only expenditure manager can delete
            if ($request->user()->role !== 'expenditure_manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only expenditure managers can delete records.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'exists:user_monthly_finances,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $deletedCount = 0;
            $failedIds = [];

            foreach ($request->ids as $id) {
                $record = UserMonthlyFinance::find($id);
                
                if ($record) {
                    $record->delete();
                    $deletedCount++;
                } else {
                    $failedIds[] = $id;
                }
            }

            return response()->json([
                'success' => true,
                'message' => $deletedCount . ' record(s) deleted successfully',
                'deleted_count' => $deletedCount,
                'failed_ids' => $failedIds
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options for dropdowns
     */
    public function getFilterOptions()
    {
        try {
            // Get distinct usernames and user_ids
            $users = UserMonthlyFinance::select('user_id', 'username')
                ->whereNotNull('username')
                ->distinct()
                ->get()
                ->map(function($item) {
                    return [
                        'value' => $item->username,
                        'label' => $item->username . ' (ID: ' . $item->user_id . ')'
                    ];
                });

            // Get distinct years from created_at
            $years = UserMonthlyFinance::selectRaw('DISTINCT YEAR(created_at) as year')
                ->whereNotNull('created_at')
                ->pluck('year')
                ->unique()
                ->sortDesc()
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'months' => UserMonthlyFinance::whereNotNull('month')->distinct()->orderBy('month')->pluck('month'),
                    'years' => $years->isEmpty() ? [date('Y')] : $years,
                    'statuses' => [
                        ['value' => '', 'label' => 'All Status'],
                        ['value' => '0', 'label' => 'Pending'],
                        ['value' => '1', 'label' => 'Approved']
                    ]
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
     * Get summary statistics
     */
    public function getSummary(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $query = UserMonthlyFinance::where('user_id', $userId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $query->count(),
                    'pending_count' => $query->where('is_approved', false)->count(),
                    'approved_count' => $query->where('is_approved', true)->count(),
                    'total_cash' => $query->sum('cash'),
                    'total_xe' => $query->sum('xe'),
                    'total_cash_xe' => $query->sum('cash_xe'),
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
     * Export all records for a user
     */
    public function export(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $records = UserMonthlyFinance::where('user_id', $userId)->orderBy('id', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'total_records' => $records->count(),
                'total_cash' => $records->sum('cash'),
                'total_xe' => $records->sum('xe'),
                'total_cash_xe' => $records->sum('cash_xe'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all data for a specific user (Manager only)
     * This deletes ALL records including approved ones
     */
    public function deleteUserData(Request $request, $userId)
    {
        try {
            if ($request->user()->role !== 'expenditure_manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only expenditure managers can delete user data.'
                ], 403);
            }

            $records = UserMonthlyFinance::where('user_id', $userId)->get();
            
            if ($records->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No records found for this user'
                ], 404);
            }

            $deletedCount = UserMonthlyFinance::where('user_id', $userId)->delete();

            return response()->json([
                'success' => true,
                'message' => "All data for User {$userId} has been deleted successfully. ({$deletedCount} records deleted)",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting user data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete only unapproved data for a specific user (Manager only)
     */
    public function deleteUnapprovedUserData(Request $request, $userId)
    {
        try {
            if ($request->user()->role !== 'expenditure_manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only expenditure managers can delete user data.'
                ], 403);
            }

            $deletedCount = UserMonthlyFinance::where('user_id', $userId)
                ->where('is_approved', false)
                ->delete();

            if ($deletedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No unapproved records found for this user'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => "All unapproved data for User {$userId} has been deleted successfully. ({$deletedCount} records deleted)",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting user data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user has uploaded data
     */
    public function checkUserUploads(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $hasUploads = UserMonthlyFinance::where('user_id', $userId)->exists();
            $pendingCount = UserMonthlyFinance::where('user_id', $userId)
                ->where('is_approved', false)
                ->count();
            $approvedCount = UserMonthlyFinance::where('user_id', $userId)
                ->where('is_approved', true)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_uploads' => $hasUploads,
                    'pending_count' => $pendingCount,
                    'approved_count' => $approvedCount,
                    'total_count' => $pendingCount + $approvedCount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking uploads: ' . $e->getMessage()
            ], 500);
        }
    }
}



