<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\MonthlyFincanceImport;
use Illuminate\Support\Facades\Validator;

class MonthlyFincanceController extends Controller
{
    /**
     * Get all records with filters and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = MonthlyFincance::query();
            
            // Apply filters
            if ($request->filled('subject')) {
                $query->where('subject', 'like', '%' . $request->subject . '%');
            }
            if ($request->filled('trno')) {
                $query->where('trno', $request->trno);
            }
            if ($request->filled('month')) {
                $query->where('month', $request->month);
            }
            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }
            if ($request->filled('head')) {
                $query->where('head', $request->head);
            }
            if ($request->filled('program')) {
                $query->where('program', $request->program);
            }
            if ($request->filled('project')) {
                $query->where('project', $request->project);
            }
            if ($request->filled('object')) {
                $query->where('object', $request->object);
            }
            if ($request->filled('dr_cr')) {
                $query->where('dr_cr', $request->dr_cr);
            }
            if ($request->filled('min_amount')) {
                $query->where('cash_xe', '>=', $request->min_amount);
            }
            if ($request->filled('max_amount')) {
                $query->where('cash_xe', '<=', $request->max_amount);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('id', 'asc')->paginate($perPage);
            
            // Calculate totals
            $totalCash = $query->sum('cash');
            $totalXe = $query->sum('xe');
            $totalCashXe = $query->sum('cash_xe');
            
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
                    'cash' => $totalCash,
                    'xe' => $totalXe,
                    'cash_xe' => $totalCashXe
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
            $record = MonthlyFincance::find($id);
            
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
     * Create new record
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'subject' => 'nullable|string|max:255',
                'trno' => 'nullable|integer',
                'month' => 'nullable|integer|between:1,12',
                'year' => 'nullable|integer|digits:4',
                'head' => 'nullable|integer',
                'program' => 'nullable|integer',
                'project' => 'nullable|integer',
                'cash' => 'nullable|numeric',
                'xe' => 'nullable|numeric',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Calculate cash_xe if not provided
            $cashXe = $request->cash_xe ?? ($request->cash + $request->xe);
            
            $record = MonthlyFincance::create([
                'subject' => $request->subject,
                'trno' => $request->trno,
                'month' => $request->month,
                'sn' => $request->sn,
                'dr_cr_code' => $request->dr_cr_code,
                'head' => $request->head,
                'program' => $request->program,
                'project' => $request->project,
                'sub_project' => $request->sub_project,
                'object' => $request->object,
                'item' => $request->item,
                'funding' => $request->funding,
                'dr_cr' => $request->dr_cr,
                'cash_xe' => $cashXe,
                'head_no' => $request->head_no,
                'year' => $request->year,
                'cash' => $request->cash ?? 0,
                'xe' => $request->xe ?? 0,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Record created successfully',
                'data' => $record
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating record: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update record
     */
    public function update(Request $request, $id)
    {
        try {
            $record = MonthlyFincance::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'subject' => 'nullable|string|max:255',
                'trno' => 'nullable|integer',
                'month' => 'nullable|integer|between:1,12',
                'year' => 'nullable|integer|digits:4',
                'cash' => 'nullable|numeric',
                'xe' => 'nullable|numeric',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Calculate cash_xe if cash or xe is updated
            $cashXe = ($request->cash ?? $record->cash) + ($request->xe ?? $record->xe);
            
            $record->update([
                'subject' => $request->subject ?? $record->subject,
                'trno' => $request->trno ?? $record->trno,
                'month' => $request->month ?? $record->month,
                'sn' => $request->sn ?? $record->sn,
                'dr_cr_code' => $request->dr_cr_code ?? $record->dr_cr_code,
                'head' => $request->head ?? $record->head,
                'program' => $request->program ?? $record->program,
                'project' => $request->project ?? $record->project,
                'sub_project' => $request->sub_project ?? $record->sub_project,
                'object' => $request->object ?? $record->object,
                'item' => $request->item ?? $record->item,
                'funding' => $request->funding ?? $record->funding,
                'dr_cr' => $request->dr_cr ?? $record->dr_cr,
                'cash_xe' => $cashXe,
                'head_no' => $request->head_no ?? $record->head_no,
                'year' => $request->year ?? $record->year,
                'cash' => $request->cash ?? $record->cash,
                'xe' => $request->xe ?? $record->xe,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating record: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete single record
     */
    public function destroy($id)
    {
        try {
            $record = MonthlyFincance::find($id);
            
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
     * Delete multiple records
     */
    public function destroyMultiple(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'exists:monthly_fincances,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $deletedCount = MonthlyFincance::whereIn('id', $request->ids)->delete();
            
            return response()->json([
                'success' => true,
                'message' => $deletedCount . ' record(s) deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Import Excel file
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
            
            $import = new MonthlyFincanceImport();
            Excel::import($import, $request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => 'Monthly finance data imported successfully',
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
     * Get filter options for dropdowns
     */
    public function getFilterOptions()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'subjects' => MonthlyFincance::whereNotNull('subject')->distinct()->pluck('subject'),
                    'months' => MonthlyFincance::whereNotNull('month')->distinct()->orderBy('month')->pluck('month'),
                    'years' => MonthlyFincance::whereNotNull('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
                    'heads' => MonthlyFincance::whereNotNull('head')->distinct()->orderBy('head')->pluck('head'),
                    'programs' => MonthlyFincance::whereNotNull('program')->distinct()->orderBy('program')->pluck('program'),
                    'projects' => MonthlyFincance::whereNotNull('project')->distinct()->orderBy('project')->pluck('project'),
                    'dr_cr' => ['Dr', 'Cr'],
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
    public function getSummary()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_cash' => MonthlyFincance::sum('cash'),
                    'total_xe' => MonthlyFincance::sum('xe'),
                    'total_cash_xe' => MonthlyFincance::sum('cash_xe'),
                    'total_records' => MonthlyFincance::count(),
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
     * Export all records
     */
    public function export()
    {
        try {
            $records = MonthlyFincance::orderBy('id', 'desc')->get();
            
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
}