<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Treasury;
use App\Imports\TreasuryImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class TreasuryController extends Controller
{
    /**
     * Display a listing of all treasury records.
     */
    public function index(Request $request)
    {
        try {
            $query = Treasury::query();
            
            // Apply filters
            if ($request->has('trno') && $request->trno !== '' && $request->trno !== null) {
                $query->where('trno', $request->trno);
            }
            if ($request->has('month') && $request->month !== '' && $request->month !== null) {
                $query->where('month', $request->month);
            }
            if ($request->has('year') && $request->year !== '' && $request->year !== null) {
                $query->where('year', $request->year);
            }
            if ($request->has('head') && $request->head !== '' && $request->head !== null) {
                $query->where('head', $request->head);
            }
            if ($request->has('program') && $request->program !== '' && $request->program !== null) {
                $query->where('program', $request->program);
            }
            if ($request->has('project') && $request->project !== '' && $request->project !== null) {
                $query->where('project', $request->project);
            }
            if ($request->has('sub_project') && $request->sub_project !== '' && $request->sub_project !== null) {
                $query->where('sub_project', $request->sub_project);
            }
            if ($request->has('object') && $request->object !== '' && $request->object !== null) {
                $query->where('object', $request->object);
            }
            if ($request->filled('dr_cr')) {
                $query->where('dr_cr', $request->dr_cr);
            }
            if ($request->filled('sn')) {
                $query->where('sn', 'like', '%' . $request->sn . '%');
            }
            
            // Date range filters
            if ($request->filled('min_cash')) {
                $query->where('cash', '>=', $request->min_cash);
            }
            if ($request->filled('max_cash')) {
                $query->where('cash', '<=', $request->max_cash);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->orderBy('trno', 'asc')
                ->paginate($perPage);
            
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
                    'total_cash' => $totalCash,
                    'total_xe' => $totalXe,
                    'total_cash_xe' => $totalCashXe,
                    'total_records' => $records->total(),
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
     * Store a newly created treasury record.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'subject' => 'nullable|string|max:255',
                'trno' => 'nullable|integer',
                'month' => 'nullable|integer|between:1,12',
                'sn' => 'nullable|string|max:255',
                'dr_cr_code' => 'nullable|integer',
                'head' => 'nullable|integer',
                'program' => 'nullable|integer',
                'project' => 'nullable|integer',
                'sub_project' => 'nullable|integer',
                'object' => 'nullable|integer',
                'item' => 'nullable|integer',
                'funding' => 'nullable|integer',
                'dr_cr' => 'nullable|string|in:DR,CR',
                'cash_xe' => 'nullable|numeric|min:0',
                'head_no' => 'nullable|integer',
                'year' => 'nullable|integer|digits:2',
                'cash' => 'nullable|numeric|min:0',
                'xe' => 'nullable|numeric|min:0',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Prepare data with proper handling of 0 values
            $data = [];
            $fields = [
                'subject', 'trno', 'month', 'sn', 'dr_cr_code', 'head', 
                'program', 'project', 'sub_project', 'object', 'item', 
                'funding', 'dr_cr', 'cash_xe', 'head_no', 'year', 'cash', 'xe'
            ];
            
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    if ($value === null || $value === '') {
                        $data[$field] = null;
                    } else {
                        $data[$field] = $value;
                    }
                }
            }
            
            $record = Treasury::create($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Treasury record created successfully',
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
     * Display the specified treasury record.
     */
    public function show($id)
    {
        try {
            $record = Treasury::find($id);
            
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
     * Update the specified treasury record.
     */
    public function update(Request $request, $id)
    {
        try {
            $record = Treasury::find($id);
            
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
                'sn' => 'nullable|string|max:255',
                'dr_cr_code' => 'nullable|integer',
                'head' => 'nullable|integer',
                'program' => 'nullable|integer',
                'project' => 'nullable|integer',
                'sub_project' => 'nullable|integer',
                'object' => 'nullable|integer',
                'item' => 'nullable|integer',
                'funding' => 'nullable|integer',
                'dr_cr' => 'nullable|string|in:DR,CR',
                'cash_xe' => 'nullable|numeric|min:0',
                'head_no' => 'nullable|integer',
                'year' => 'nullable|integer|digits:2',
                'cash' => 'nullable|numeric|min:0',
                'xe' => 'nullable|numeric|min:0',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Prepare data with proper handling of 0 values
            $data = [];
            $fields = [
                'subject', 'trno', 'month', 'sn', 'dr_cr_code', 'head', 
                'program', 'project', 'sub_project', 'object', 'item', 
                'funding', 'dr_cr', 'cash_xe', 'head_no', 'year', 'cash', 'xe'
            ];
            
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    if ($value === null || $value === '') {
                        $data[$field] = null;
                    } else {
                        $data[$field] = $value;
                    }
                }
            }
            
            $record->update($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Treasury record updated successfully',
                'data' => $record->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified treasury record.
     */
    public function destroy($id)
    {
        try {
            $record = Treasury::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $record->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Treasury record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple treasury records.
     */
    public function destroyMultiple(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'exists:treasury,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $deletedCount = Treasury::whereIn('id', $request->ids)->delete();
            
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
     * Import Excel file.
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
            
            $import = new TreasuryImport();
            Excel::import($import, $request->file('file'));
            
            $response = [
                'success' => true,
                'message' => 'Treasury records imported successfully',
                'imported_count' => $import->getImportedCount(),
                'skipped_count' => $import->getSkippedCount(),
            ];
            
            $errors = $import->getErrors();
            if (!empty($errors)) {
                $response['errors'] = $errors;
            }
            
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options for dropdowns.
     */
    public function getFilterOptions()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'months' => Treasury::whereNotNull('month')->distinct()->orderBy('month')->pluck('month'),
                    'years' => Treasury::whereNotNull('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
                    'heads' => Treasury::whereNotNull('head')->distinct()->orderBy('head')->pluck('head'),
                    'programs' => Treasury::whereNotNull('program')->distinct()->orderBy('program')->pluck('program'),
                    'projects' => Treasury::whereNotNull('project')->distinct()->orderBy('project')->pluck('project'),
                    'sub_projects' => Treasury::whereNotNull('sub_project')->distinct()->orderBy('sub_project')->pluck('sub_project'),
                    'objects' => Treasury::whereNotNull('object')->distinct()->orderBy('object')->pluck('object'),
                    'dr_cr' => Treasury::whereNotNull('dr_cr')->distinct()->orderBy('dr_cr')->pluck('dr_cr'),
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
     * Get summary statistics.
     */
    public function getSummary()
    {
        try {
            $totalRecords = Treasury::count();
            $totalCash = Treasury::sum('cash');
            $totalXe = Treasury::sum('xe');
            $totalCashXe = Treasury::sum('cash_xe');
            $avgCash = Treasury::avg('cash');
            $maxCash = Treasury::max('cash');
            $minCash = Treasury::min('cash');
            
            // Summary by head
            $byHead = Treasury::selectRaw('head, COUNT(*) as count, SUM(cash) as total_cash, SUM(xe) as total_xe, SUM(cash_xe) as total_cash_xe')
                ->groupBy('head')
                ->orderBy('head')
                ->get();
            
            // Summary by month
            $byMonth = Treasury::selectRaw('month, COUNT(*) as count, SUM(cash) as total_cash, SUM(xe) as total_xe, SUM(cash_xe) as total_cash_xe')
                ->groupBy('month')
                ->orderBy('month')
                ->get();
            
            // Summary by year
            $byYear = Treasury::selectRaw('year, COUNT(*) as count, SUM(cash) as total_cash, SUM(xe) as total_xe, SUM(cash_xe) as total_cash_xe')
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $totalRecords,
                    'total_cash' => $totalCash,
                    'total_xe' => $totalXe,
                    'total_cash_xe' => $totalCashXe,
                    'average_cash' => round($avgCash, 2),
                    'max_cash' => $maxCash,
                    'min_cash' => $minCash,
                    'by_head' => $byHead,
                    'by_month' => $byMonth,
                    'by_year' => $byYear,
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
     * Export all records.
     */
    public function export()
    {
        try {
            $records = Treasury::orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->orderBy('trno', 'asc')
                ->get();
            
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