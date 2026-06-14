<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OpeningBalance;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\OpeningBalanceImport;
use Illuminate\Support\Facades\Validator;

class OpeningBalanceController extends Controller
{
    /**
     * Get all opening balances with filters and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = OpeningBalance::query();
            
            // Apply filters
            if ($request->filled('head')) {
                $query->where('head', $request->head);
            }
            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }
            if ($request->filled('min_balance')) {
                $query->where('opening_balance', '>=', $request->min_balance);
            }
            if ($request->filled('max_balance')) {
                $query->where('opening_balance', '<=', $request->max_balance);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('year', 'desc')->orderBy('head', 'asc')->paginate($perPage);
            
            // Calculate totals
            $totalBalance = $query->sum('opening_balance');
            $totalRecords = $query->count();
            
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
                    'total_balance' => $totalBalance,
                    'total_records' => $totalRecords
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
            $record = OpeningBalance::find($id);
            
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
                'head' => 'required|integer',
                'year' => 'required|integer|digits:4',
                'opening_balance' => 'required|numeric'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check if record already exists for this head and year
            $existing = OpeningBalance::where('head', $request->head)
                ->where('year', $request->year)
                ->first();
                
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Opening balance already exists for this Head and Year'
                ], 422);
            }
            
            $record = OpeningBalance::create($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Opening balance created successfully',
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
            $record = OpeningBalance::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'head' => 'sometimes|integer',
                'year' => 'sometimes|integer|digits:4',
                'opening_balance' => 'sometimes|numeric'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check for duplicate if head or year is being changed
            if (($request->has('head') || $request->has('year')) && 
                ($request->head != $record->head || $request->year != $record->year)) {
                
                $existing = OpeningBalance::where('head', $request->head ?? $record->head)
                    ->where('year', $request->year ?? $record->year)
                    ->first();
                    
                if ($existing && $existing->id != $id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Opening balance already exists for this Head and Year'
                    ], 422);
                }
            }
            
            $record->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Opening balance updated successfully',
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
            $record = OpeningBalance::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $record->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Opening balance deleted successfully'
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
                'ids.*' => 'exists:opening_balances,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $deletedCount = OpeningBalance::whereIn('id', $request->ids)->delete();
            
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
            
            $import = new OpeningBalanceImport();
            Excel::import($import, $request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => 'Opening balances imported successfully',
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
                    'years' => OpeningBalance::whereNotNull('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
                    'heads' => OpeningBalance::whereNotNull('head')->distinct()->orderBy('head')->pluck('head'),
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
            $totalBalance = OpeningBalance::sum('opening_balance');
            $totalRecords = OpeningBalance::count();
            $averageBalance = OpeningBalance::avg('opening_balance');
            $maxBalance = OpeningBalance::max('opening_balance');
            $minBalance = OpeningBalance::min('opening_balance');
            
            // Group by year
            $byYear = OpeningBalance::selectRaw('year, SUM(opening_balance) as total, COUNT(*) as count')
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_balance' => $totalBalance,
                    'total_records' => $totalRecords,
                    'average_balance' => round($averageBalance, 2),
                    'max_balance' => $maxBalance,
                    'min_balance' => $minBalance,
                    'by_year' => $byYear
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
            $records = OpeningBalance::orderBy('year', 'desc')->orderBy('head', 'asc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'total_records' => $records->count(),
                'total_balance' => $records->sum('opening_balance'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}