<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RevenueReceipt;
use App\Models\AccountNumber;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RevenueReceiptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            Log::info('Fetching revenue receipts', $request->all());
            
            $query = RevenueReceipt::with(['accountNumber', 'estimate']);

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

            // Filter by month
            if ($request->filled('month')) {
                $query->where('month', $request->month);
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
            $revenueReceipts = $query->orderBy('id', 'desc')->paginate($perPage);

            Log::info('Revenue receipts fetched successfully', ['count' => $revenueReceipts->count()]);

            return response()->json([
                'success' => true,
                'data' => $revenueReceipts->items(),
                'pagination' => [
                    'current_page' => $revenueReceipts->currentPage(),
                    'per_page' => $revenueReceipts->perPage(),
                    'total' => $revenueReceipts->total(),
                    'last_page' => $revenueReceipts->lastPage(),
                ],
                'summary' => [
                    'total_amount' => $query->sum('amount'),
                    'total_records' => $query->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching revenue receipts: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching revenue receipts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            Log::info('Creating revenue receipt', $request->all());

            $validator = Validator::make($request->all(), [
                'account_number_id' => 'required|exists:account_numbers,id',
                'amount' => 'required|numeric|min:0',
                'month' => 'required|string|max:255',
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
            $exists = RevenueReceipt::where('account_number_id', $request->account_number_id)
                ->where('estimate_id', $request->estimate_id)
                ->where('month', $request->month)
                ->where('year', $request->year)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This combination of Account Number, Estimate, Month, and Year already exists.'
                ], 422);
            }

            $revenueReceipt = RevenueReceipt::create([
                'account_number_id' => $request->account_number_id,
                'amount' => $request->amount,
                'month' => $request->month,
                'year' => $request->year,
                'estimate_id' => $request->estimate_id,
            ]);

            // Load the relationships for response
            $revenueReceipt->load(['accountNumber', 'estimate']);

            Log::info('Revenue receipt created successfully', ['id' => $revenueReceipt->id]);

            return response()->json([
                'success' => true,
                'message' => 'Revenue receipt created successfully',
                'data' => $revenueReceipt
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating revenue receipt: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error creating revenue receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $revenueReceipt = RevenueReceipt::with(['accountNumber', 'estimate'])->find($id);

            if (!$revenueReceipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Revenue receipt not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $revenueReceipt
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching revenue receipt: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching revenue receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $revenueReceipt = RevenueReceipt::find($id);

            if (!$revenueReceipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Revenue receipt not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'account_number_id' => 'sometimes|exists:account_numbers,id',
                'amount' => 'sometimes|numeric|min:0',
                'month' => 'sometimes|string|max:255',
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
                $request->filled('month') || $request->filled('year')) {
                $accountNumberId = $request->account_number_id ?? $revenueReceipt->account_number_id;
                $estimateId = $request->estimate_id ?? $revenueReceipt->estimate_id;
                $month = $request->month ?? $revenueReceipt->month;
                $year = $request->year ?? $revenueReceipt->year;

                $exists = RevenueReceipt::where('account_number_id', $accountNumberId)
                    ->where('estimate_id', $estimateId)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This combination of Account Number, Estimate, Month, and Year already exists.'
                    ], 422);
                }
            }

            $revenueReceipt->update($request->only([
                'account_number_id',
                'amount',
                'month',
                'year',
                'estimate_id'
            ]));

            $revenueReceipt->load(['accountNumber', 'estimate']);

            Log::info('Revenue receipt updated successfully', ['id' => $revenueReceipt->id]);

            return response()->json([
                'success' => true,
                'message' => 'Revenue receipt updated successfully',
                'data' => $revenueReceipt
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating revenue receipt: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating revenue receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $revenueReceipt = RevenueReceipt::find($id);

            if (!$revenueReceipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Revenue receipt not found'
                ], 404);
            }

            $revenueReceipt->delete();

            Log::info('Revenue receipt deleted successfully', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Revenue receipt deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting revenue receipt: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting revenue receipt: ' . $e->getMessage()
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
                'ids.*' => 'exists:revenue_receipts,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $deletedCount = RevenueReceipt::whereIn('id', $request->ids)->delete();

            Log::info('Multiple revenue receipts deleted', ['count' => $deletedCount]);

            return response()->json([
                'success' => true,
                'message' => $deletedCount . ' record(s) deleted successfully',
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting multiple revenue receipts: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue receipts by account number
     */
    public function getByAccountNumber($accountNumberId)
    {
        try {
            $revenueReceipts = RevenueReceipt::with(['accountNumber', 'estimate'])
                ->where('account_number_id', $accountNumberId)
                ->orderBy('estimate_id', 'asc')
                ->orderBy('month', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $revenueReceipts,
                'summary' => [
                    'total_amount' => $revenueReceipts->sum('amount'),
                    'total_records' => $revenueReceipts->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching revenue receipts by account: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching revenue receipts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue receipts by estimate
     */
    public function getByEstimate($estimateId)
    {
        try {
            $revenueReceipts = RevenueReceipt::with(['accountNumber', 'estimate'])
                ->where('estimate_id', $estimateId)
                ->orderBy('month', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $revenueReceipts,
                'summary' => [
                    'total_amount' => $revenueReceipts->sum('amount'),
                    'total_records' => $revenueReceipts->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching revenue receipts by estimate: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching revenue receipts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary by account number
     */
    public function getSummary()
    {
        try {
            Log::info('Fetching revenue receipts summary');
            
            $summary = RevenueReceipt::with(['accountNumber', 'estimate'])
                ->selectRaw('account_number_id, COUNT(*) as total_records, SUM(amount) as total_amount')
                ->groupBy('account_number_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'account_number_id' => $item->account_number_id,
                        'account_number' => $item->accountNumber->account_number ?? null,
                        'account_description' => $item->accountNumber->description ?? null,
                        'total_records' => $item->total_records,
                        'total_amount' => $item->total_amount,
                    ];
                });

            $grandTotal = RevenueReceipt::sum('amount');

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
     * Get month wise summary
     */
    public function getMonthWiseSummary()
    {
        try {
            Log::info('Fetching month wise summary');
            
            $summary = RevenueReceipt::with(['accountNumber', 'estimate'])
                ->selectRaw('month, year, COUNT(*) as total_records, SUM(amount) as total_amount')
                ->groupBy('month', 'year')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching month wise summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching month wise summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options for dropdowns
     */
    public function getFilterOptions()
    {
        try {
            $months = RevenueReceipt::getAvailableMonths();
            $years = RevenueReceipt::getAvailableYears();
            
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
                    'months' => $months,
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
            $query = RevenueReceipt::with(['accountNumber', 'estimate']);

            // Apply filters
            if ($request->filled('account_number_id')) {
                $query->where('account_number_id', $request->account_number_id);
            }
            if ($request->filled('month')) {
                $query->where('month', $request->month);
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
            Log::error('Error exporting revenue receipts: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error exporting revenue receipts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics
     */
    public function getStatistics()
    {
        try {
            $totalRecords = RevenueReceipt::count();
            $totalAmount = RevenueReceipt::sum('amount');
            $averageAmount = $totalRecords > 0 ? $totalAmount / $totalRecords : 0;
            $maxAmount = RevenueReceipt::max('amount') ?? 0;
            $minAmount = RevenueReceipt::min('amount') ?? 0;

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
     * Get records by month
     */
    public function getByMonth($month)
    {
        try {
            $records = RevenueReceipt::with(['accountNumber', 'estimate'])
                ->where('month', $month)
                ->orderBy('estimate_id', 'asc')
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
            Log::error('Error fetching records by month: ' . $e->getMessage());
            
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
                'month' => 'required|string',
                'year' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $exists = RevenueReceipt::where('account_number_id', $request->account_number_id)
                ->where('estimate_id', $request->estimate_id)
                ->where('month', $request->month)
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