<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RevenueOpeningBalance;
use App\Models\AccountNumber;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RevenueOpeningBalanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            Log::info('Fetching revenue opening balances', $request->all());
            
            $query = RevenueOpeningBalance::with(['accountNumber', 'estimate']);

            // Search functionality
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Filter by account number
            if ($request->filled('account_number_id')) {
                $query->where('account_number_id', $request->account_number_id);
            }

            // Filter by estimate
            if ($request->filled('estimate_id')) {
                $query->where('estimate_id', $request->estimate_id);
            }

            // Filter by year
            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }

            // Filter by amount range
            if ($request->filled('min_amount')) {
                $query->where('amount', '>=', $request->min_amount);
            }
            if ($request->filled('max_amount')) {
                $query->where('amount', '<=', $request->max_amount);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $openingBalances = $query->orderBy('id', 'desc')->paginate($perPage);

            Log::info('Opening balances fetched successfully', ['count' => $openingBalances->count()]);

            return response()->json([
                'success' => true,
                'data' => $openingBalances->items(),
                'pagination' => [
                    'current_page' => $openingBalances->currentPage(),
                    'per_page' => $openingBalances->perPage(),
                    'total' => $openingBalances->total(),
                    'last_page' => $openingBalances->lastPage(),
                ],
                'summary' => [
                    'total_amount' => $query->sum('amount'),
                    'total_records' => $query->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching opening balances: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching opening balances: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            Log::info('Creating revenue opening balance', $request->all());

            $validator = Validator::make($request->all(), [
                'account_number_id' => 'required|exists:account_numbers,id',
                'amount' => 'required|numeric|min:0',
                'year' => 'required|integer|min:2000|max:2100',
                'estimate_id' => 'nullable|exists:estimates,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicate entry (unique constraint)
            $exists = RevenueOpeningBalance::where('account_number_id', $request->account_number_id)
                ->where('estimate_id', $request->estimate_id)
                ->where('year', $request->year)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This combination of Account Number, Estimate, and Year already exists.'
                ], 422);
            }

            $openingBalance = RevenueOpeningBalance::create([
                'account_number_id' => $request->account_number_id,
                'amount' => $request->amount,
                'year' => $request->year,
                'estimate_id' => $request->estimate_id,
            ]);

            // Load the relationships for response
            $openingBalance->load(['accountNumber', 'estimate']);

            Log::info('Opening balance created successfully', ['id' => $openingBalance->id]);

            return response()->json([
                'success' => true,
                'message' => 'Opening balance created successfully',
                'data' => $openingBalance
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating opening balance: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error creating opening balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $openingBalance = RevenueOpeningBalance::with(['accountNumber', 'estimate'])->find($id);

            if (!$openingBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Opening balance not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $openingBalance
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching opening balance: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching opening balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $openingBalance = RevenueOpeningBalance::find($id);

            if (!$openingBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Opening balance not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'account_number_id' => 'sometimes|exists:account_numbers,id',
                'amount' => 'sometimes|numeric|min:0',
                'year' => 'sometimes|integer|min:2000|max:2100',
                'estimate_id' => 'nullable|exists:estimates,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicate if fields are being updated
            if ($request->filled('account_number_id') || $request->filled('estimate_id') || 
                $request->filled('year')) {
                $accountNumberId = $request->account_number_id ?? $openingBalance->account_number_id;
                $estimateId = $request->estimate_id ?? $openingBalance->estimate_id;
                $year = $request->year ?? $openingBalance->year;

                $exists = RevenueOpeningBalance::where('account_number_id', $accountNumberId)
                    ->where('estimate_id', $estimateId)
                    ->where('year', $year)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This combination of Account Number, Estimate, and Year already exists.'
                    ], 422);
                }
            }

            $openingBalance->update($request->only([
                'account_number_id',
                'amount',
                'year',
                'estimate_id'
            ]));

            $openingBalance->load(['accountNumber', 'estimate']);

            Log::info('Opening balance updated successfully', ['id' => $openingBalance->id]);

            return response()->json([
                'success' => true,
                'message' => 'Opening balance updated successfully',
                'data' => $openingBalance
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating opening balance: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating opening balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $openingBalance = RevenueOpeningBalance::find($id);

            if (!$openingBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Opening balance not found'
                ], 404);
            }

            $openingBalance->delete();

            Log::info('Opening balance deleted successfully', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Opening balance deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting opening balance: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting opening balance: ' . $e->getMessage()
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
                'ids.*' => 'exists:revenue_opening_balances,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $deletedCount = RevenueOpeningBalance::whereIn('id', $request->ids)->delete();

            Log::info('Multiple opening balances deleted', ['count' => $deletedCount]);

            return response()->json([
                'success' => true,
                'message' => $deletedCount . ' record(s) deleted successfully',
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting multiple opening balances: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get opening balances by account number
     */
    public function getByAccountNumber($accountNumberId)
    {
        try {
            $openingBalances = RevenueOpeningBalance::with(['accountNumber', 'estimate'])
                ->where('account_number_id', $accountNumberId)
                ->orderBy('year', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $openingBalances,
                'summary' => [
                    'total_amount' => $openingBalances->sum('amount'),
                    'total_records' => $openingBalances->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching opening balances by account: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching opening balances: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get opening balances by estimate
     */
    public function getByEstimate($estimateId)
    {
        try {
            $openingBalances = RevenueOpeningBalance::with(['accountNumber', 'estimate'])
                ->where('estimate_id', $estimateId)
                ->orderBy('year', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $openingBalances,
                'summary' => [
                    'total_amount' => $openingBalances->sum('amount'),
                    'total_records' => $openingBalances->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching opening balances by estimate: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching opening balances: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary by account number
     */
    public function getSummary()
    {
        try {
            Log::info('Fetching opening balances summary');
            
            $summary = RevenueOpeningBalance::getSummary();
            $grandTotal = RevenueOpeningBalance::sum('amount');

            return response()->json([
                'success' => true,
                'data' => $summary,
                'grand_total' => $grandTotal
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get year wise summary
     */
    public function getYearWiseSummary()
    {
        try {
            Log::info('Fetching year wise summary');
            
            $summary = RevenueOpeningBalance::with(['accountNumber', 'estimate'])
                ->selectRaw('year, COUNT(*) as total_records, SUM(amount) as total_amount')
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching year wise summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching year wise summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options for dropdowns
     */
    public function getFilterOptions()
    {
        try {
            $years = RevenueOpeningBalance::getAvailableYears();
            
            // Get distinct account numbers with descriptions
            $accounts = AccountNumber::select('id', 'account_number', 'description')
                ->orderBy('account_number')
                ->get();

            // Get estimates with revenue code names
            $estimates = Estimate::select('id', 'revenue_code_name', 'head', 'project', 'object')
                ->whereNotNull('revenue_code_name')
                ->orderBy('revenue_code_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'accounts' => $accounts,
                    'years' => $years,
                    'estimates' => $estimates,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching filter options: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching filter options: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue code options from estimates table for dropdown
     */
    public function getRevenueCodeOptions()
    {
        try {
            Log::info('Fetching revenue code options from estimates');
            
            $estimates = Estimate::select('id', 'revenue_code_name', 'head', 'program', 'project', 'sub_project', 'object')
                ->whereNotNull('revenue_code_name')
                ->orderBy('revenue_code_name', 'asc')
                ->get();
            
            $options = $estimates->map(function ($estimate) {
                return [
                    'id' => $estimate->id,
                    'revenue_code' => $estimate->revenue_code_name,
                    'revenue_code_name' => $estimate->revenue_code_name,
                    'head' => $estimate->head,
                    'program' => $estimate->program,
                    'project' => $estimate->project,
                    'sub_project' => $estimate->sub_project,
                    'object' => $estimate->object,
                ];
            });

            Log::info('Revenue code options fetched successfully', ['count' => $options->count()]);

            return response()->json([
                'success' => true,
                'data' => $options,
                'count' => $options->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching revenue code options: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching revenue code options: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search revenue codes from estimates
     */
    public function searchRevenueCodes(Request $request)
    {
        try {
            $search = $request->get('q', '');
            
            $query = Estimate::select('id', 'revenue_code_name', 'head', 'program', 'project', 'sub_project', 'object')
                ->whereNotNull('revenue_code_name');
            
            if (!empty($search)) {
                $query->where('revenue_code_name', 'LIKE', "%{$search}%");
            }
            
            $estimates = $query->orderBy('revenue_code_name', 'asc')->limit(20)->get();
            
            $options = $estimates->map(function ($estimate) {
                return [
                    'id' => $estimate->id,
                    'revenue_code' => $estimate->revenue_code_name,
                    'revenue_code_name' => $estimate->revenue_code_name,
                    'head' => $estimate->head,
                    'program' => $estimate->program,
                    'project' => $estimate->project,
                    'sub_project' => $estimate->sub_project,
                    'object' => $estimate->object,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $options
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching revenue codes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error searching revenue codes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export all records
     */
    public function export(Request $request)
    {
        try {
            $query = RevenueOpeningBalance::with(['accountNumber', 'estimate']);

            // Apply filters
            if ($request->filled('account_number_id')) {
                $query->where('account_number_id', $request->account_number_id);
            }
            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }
            if ($request->filled('estimate_id')) {
                $query->where('estimate_id', $request->estimate_id);
            }

            $records = $query->orderBy('id', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $records,
                'total_records' => $records->count(),
                'total_amount' => $records->sum('amount'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error exporting opening balances: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error exporting opening balances: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics
     */
    public function getStatistics()
    {
        try {
            $totalRecords = RevenueOpeningBalance::count();
            $totalAmount = RevenueOpeningBalance::sum('amount');
            $averageAmount = $totalRecords > 0 ? $totalAmount / $totalRecords : 0;
            $maxAmount = RevenueOpeningBalance::max('amount') ?? 0;
            $minAmount = RevenueOpeningBalance::min('amount') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $totalRecords,
                    'total_amount' => $totalAmount,
                    'average_amount' => $averageAmount,
                    'max_amount' => $maxAmount,
                    'min_amount' => $minAmount,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching statistics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get records by year
     */
    public function getByYear($year)
    {
        try {
            $records = RevenueOpeningBalance::with(['accountNumber', 'estimate'])
                ->where('year', $year)
                ->orderBy('account_number_id', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $records,
                'summary' => [
                    'total_amount' => $records->sum('amount'),
                    'total_records' => $records->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching records by year: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a record exists
     */
    public function checkExists(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_number_id' => 'required|exists:account_numbers,id',
                'estimate_id' => 'required|exists:estimates,id',
                'year' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $exists = RevenueOpeningBalance::where('account_number_id', $request->account_number_id)
                ->where('estimate_id', $request->estimate_id)
                ->where('year', $request->year)
                ->exists();

            return response()->json([
                'success' => true,
                'exists' => $exists
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking existence: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking existence: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue code names from estimates for dropdown (alias for getRevenueCodeOptions)
     */
    public function getRevenueCodeNames()
    {
        return $this->getRevenueCodeOptions();
    }
}
