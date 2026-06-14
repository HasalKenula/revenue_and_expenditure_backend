<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ImpressSettlement;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ImpressSettlementImport;
use Illuminate\Support\Facades\Validator;

class ImpressSettlementController extends Controller
{
    /**
     * Get all impress settlements with filters and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = ImpressSettlement::query();
            
            // Apply filters
            if ($request->filled('head')) {
                $query->where('head', $request->head);
            }
            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }
            if ($request->filled('month')) {
                $query->where('month', $request->month);
            }
            if ($request->filled('min_amount')) {
                $query->where('amount', '>=', $request->min_amount);
            }
            if ($request->filled('max_amount')) {
                $query->where('amount', '<=', $request->max_amount);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->orderBy('head', 'asc')
                ->paginate($perPage);
            
            // Calculate totals
            $totalAmount = $query->sum('amount');
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
                    'total_amount' => $totalAmount,
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
            $record = ImpressSettlement::find($id);
            
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
                'month' => 'required|integer|between:1,12',
                'amount' => 'required|numeric|min:0'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check if record already exists
            $existing = ImpressSettlement::where('head', $request->head)
                ->where('year', $request->year)
                ->where('month', $request->month)
                ->first();
                
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impress settlement already exists for this Head, Year, and Month'
                ], 422);
            }
            
            $record = ImpressSettlement::create($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Impress settlement created successfully',
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
            $record = ImpressSettlement::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'head' => 'sometimes|integer',
                'year' => 'sometimes|integer|digits:4',
                'month' => 'sometimes|integer|between:1,12',
                'amount' => 'sometimes|numeric|min:0'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check for duplicate if head/year/month is being changed
            $head = $request->head ?? $record->head;
            $year = $request->year ?? $record->year;
            $month = $request->month ?? $record->month;
            
            $existing = ImpressSettlement::where('head', $head)
                ->where('year', $year)
                ->where('month', $month)
                ->where('id', '!=', $id)
                ->first();
                
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impress settlement already exists for this Head, Year, and Month'
                ], 422);
            }
            
            $record->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Impress settlement updated successfully',
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
            $record = ImpressSettlement::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $record->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Impress settlement deleted successfully'
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
                'ids.*' => 'exists:impress_settlements,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $deletedCount = ImpressSettlement::whereIn('id', $request->ids)->delete();
            
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
            
            $import = new ImpressSettlementImport();
            Excel::import($import, $request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => 'Impress settlements imported successfully',
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
                    'years' => ImpressSettlement::whereNotNull('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
                    'heads' => ImpressSettlement::whereNotNull('head')->distinct()->orderBy('head')->pluck('head'),
                    'months' => [
                        ['value' => 1, 'label' => 'January'],
                        ['value' => 2, 'label' => 'February'],
                        ['value' => 3, 'label' => 'March'],
                        ['value' => 4, 'label' => 'April'],
                        ['value' => 5, 'label' => 'May'],
                        ['value' => 6, 'label' => 'June'],
                        ['value' => 7, 'label' => 'July'],
                        ['value' => 8, 'label' => 'August'],
                        ['value' => 9, 'label' => 'September'],
                        ['value' => 10, 'label' => 'October'],
                        ['value' => 11, 'label' => 'November'],
                        ['value' => 12, 'label' => 'December'],
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
    public function getSummary()
    {
        try {
            $totalAmount = ImpressSettlement::sum('amount');
            $totalRecords = ImpressSettlement::count();
            $averageAmount = ImpressSettlement::avg('amount');
            $maxAmount = ImpressSettlement::max('amount');
            $minAmount = ImpressSettlement::min('amount');
            
            // Group by year
            $byYear = ImpressSettlement::selectRaw('year, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get();
            
            // Group by month
            $byMonth = ImpressSettlement::selectRaw('month, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_amount' => $totalAmount,
                    'total_records' => $totalRecords,
                    'average_amount' => round($averageAmount, 2),
                    'max_amount' => $maxAmount,
                    'min_amount' => $minAmount,
                    'by_year' => $byYear,
                    'by_month' => $byMonth
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
            $records = ImpressSettlement::orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->orderBy('head', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'total_records' => $records->count(),
                'total_amount' => $records->sum('amount'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}