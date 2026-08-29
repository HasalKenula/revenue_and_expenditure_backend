<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RevenueAccountData;
use App\Models\RevenueReceipt;
use App\Models\RevenueOpeningBalance;
use App\Models\AccountNumber;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MoneyTransistsController extends Controller
{
    /**
     * Helper function to pad number with leading zeros
     */
    private function padNumber($value, $length = 2)
    {
        if ($value === null || $value === '') {
            return str_repeat('0', $length);
        }
        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Format revenue code as head-project-object
     */
    private function formatRevenueCode($estimate)
    {
        if (!$estimate) {
            return '-';
        }

        $head = $this->padNumber($estimate->head, 4);
        $project = $this->padNumber($estimate->project, 2);
        $object = $this->padNumber($estimate->object, 2);
        
        return "{$head}-{$project}-{$object}";
    }

    /**
     * Format estimate display with revenue code name and code
     */
    private function formatEstimateDisplay($estimate)
    {
        if (!$estimate) {
            return '-';
        }
        
        $revenueCode = $this->formatRevenueCode($estimate);
        $revenueCodeName = $estimate->revenue_code_name ?? 'Unknown';
        
        return "{$revenueCodeName} ({$revenueCode})";
    }

    /**
     * Get Money Transists Report Data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $accountNumberId = $request->input('account_number_id');
            $estimateId = $request->input('estimate_id');

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            Log::info('MoneyTransists getData called', [
                'year' => $year,
                'account_number_id' => $accountNumberId,
                'estimate_id' => $estimateId
            ]);

            $months = range(1, 12);
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Build query for account numbers
            $accountQuery = AccountNumber::query();
            if ($accountNumberId) {
                $accountQuery->where('id', $accountNumberId);
            }
            $accounts = $accountQuery->orderBy('account_number')->get();

            $results = [];
            
            // Initialize grand totals with zeros
            $grandTotals = [
                'opening_balance' => 0,
                'revenue_collection' => 0,
                'receipts' => 0,
                'transists' => 0
            ];

            // Store all estimate displays for the header
            $allEstimateDisplays = [];

            foreach ($accounts as $account) {
                // Get opening balance for this account for the selected year
                $previousYear = $year - 1;
                
                $openingBalanceQuery = RevenueOpeningBalance::where('account_number_id', $account->id);
                
                if ($estimateId) {
                    $openingBalanceQuery->where('estimate_id', $estimateId);
                }
                $openingBalanceQuery->where('year', $previousYear);
                $openingBalanceAmount = $openingBalanceQuery->sum('amount') ?? 0;

                // Initialize monthly data arrays
                $monthlyCollection = array_fill(1, 12, 0);
                $monthlyReceipts = array_fill(1, 12, 0);

                // Get revenue collection and receipts for EACH month
                foreach ($months as $monthNum) {
                    $monthName = $monthNames[$monthNum];
                    
                    // Revenue Collection from revenue_account_data
                    $collectionQuery = RevenueAccountData::where('account_number_id', $account->id)
                        ->where('month', $monthName)
                        ->where('year', $year);
                    
                    if ($estimateId) {
                        $collectionQuery->where('estimate_id', $estimateId);
                    }
                    
                    $collectionAmount = $collectionQuery->sum('amount') ?? 0;
                    $monthlyCollection[$monthNum] = $collectionAmount;

                    // Receipts from revenue_receipts
                    $receiptQuery = RevenueReceipt::where('account_number_id', $account->id)
                        ->where('month', $monthName)
                        ->where('year', $year);
                    
                    if ($estimateId) {
                        $receiptQuery->where('estimate_id', $estimateId);
                    }
                    
                    $receiptAmount = $receiptQuery->sum('amount') ?? 0;
                    $monthlyReceipts[$monthNum] = $receiptAmount;
                }

                // Calculate monthly transists and running balance
                $runningBalance = $openingBalanceAmount;
                $monthlyData = [];
                
                // Initialize totals for this account
                $totalRevenueCollection = 0;
                $totalReceipts = 0;
                $totalOpeningBalance = 0;
                
                foreach ($months as $monthNum) {
                    $collection = $monthlyCollection[$monthNum] ?? 0;
                    $receipts = $monthlyReceipts[$monthNum] ?? 0;
                    
                    // Accumulate totals for ALL months
                    $totalRevenueCollection += $collection;
                    $totalReceipts += $receipts;
                    
                    $transists = $runningBalance + $collection - $receipts;
                    
                    $monthlyData[$monthNum] = [
                        'opening_balance' => $runningBalance,
                        'revenue_collection' => $collection,
                        'receipts' => $receipts,
                        'transists' => $transists
                    ];
                    
                    // Accumulate opening balance for ALL months
                    $totalOpeningBalance += $runningBalance;
                    
                    $runningBalance = $transists;
                }

                // Cash IN Transist (December's closing balance)
                $cashInTransist = $runningBalance;

                // Only include accounts with any data
                $hasData = false;
                foreach ($months as $monthNum) {
                    if ($monthlyData[$monthNum]['opening_balance'] != 0 || 
                        $monthlyData[$monthNum]['revenue_collection'] != 0 || 
                        $monthlyData[$monthNum]['receipts'] != 0 || 
                        $monthlyData[$monthNum]['transists'] != 0) {
                        $hasData = true;
                        break;
                    }
                }

                if ($hasData) {
                    $row = [
                        'account_number_id' => $account->id,
                        'account_number' => $account->account_number,
                        'account_description' => $account->description ?? '-',
                        'monthly_data' => $monthlyData,
                        'total_opening_balance' => $totalOpeningBalance,
                        'total_revenue_collection' => $totalRevenueCollection,
                        'total_receipts' => $totalReceipts,
                        'total_transists' => $cashInTransist
                    ];

                    // If no specific estimate is selected, get all estimate displays for this account
                    if (!$estimateId) {
                        // Get all unique estimate IDs for this account
                        $estimateIdsFromCollection = RevenueAccountData::where('account_number_id', $account->id)
                            ->where('year', $year)
                            ->whereNotNull('estimate_id')
                            ->distinct()
                            ->pluck('estimate_id')
                            ->toArray();
                        
                        $estimateIdsFromReceipts = RevenueReceipt::where('account_number_id', $account->id)
                            ->where('year', $year)
                            ->whereNotNull('estimate_id')
                            ->distinct()
                            ->pluck('estimate_id')
                            ->toArray();
                        
                        $estimateIdsFromOpening = RevenueOpeningBalance::where('account_number_id', $account->id)
                            ->whereNotNull('estimate_id')
                            ->distinct()
                            ->pluck('estimate_id')
                            ->toArray();
                        
                        $allEstimateIds = array_unique(array_merge(
                            $estimateIdsFromCollection,
                            $estimateIdsFromReceipts,
                            $estimateIdsFromOpening
                        ));

                        // Get estimate displays for the header
                        foreach ($allEstimateIds as $estId) {
                            if ($estId) {
                                $estimate = Estimate::find($estId);
                                if ($estimate) {
                                    $display = $this->formatEstimateDisplay($estimate);
                                    if (!in_array($display, $allEstimateDisplays)) {
                                        $allEstimateDisplays[] = $display;
                                    }
                                }
                            }
                        }
                    }

                    $results[] = $row;

                    // Add to grand totals - SUM ALL MONTHS for each account
                    $grandTotals['opening_balance'] += $totalOpeningBalance;
                    $grandTotals['revenue_collection'] += $totalRevenueCollection;
                    $grandTotals['receipts'] += $totalReceipts;
                    $grandTotals['transists'] += $cashInTransist;
                    
                    // Log the values for debugging
                    Log::info('Account totals', [
                        'account_number' => $account->account_number,
                        'total_revenue_collection' => $totalRevenueCollection,
                        'total_receipts' => $totalReceipts,
                        'total_opening_balance' => $totalOpeningBalance,
                        'cashInTransist' => $cashInTransist
                    ]);
                }
            }

            // Log grand totals for debugging
            Log::info('Grand Totals', $grandTotals);

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'grand_totals' => $grandTotals,
                    'year' => $year,
                    'month_names' => $monthNames,
                    'months' => $months,
                    'total_records' => count($results),
                    'selected_account' => $accountNumberId,
                    'selected_estimate' => $estimateId,
                    'estimate_displays' => $allEstimateDisplays
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in MoneyTransists getData: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get filter options endpoint
     */
    public function getFilterOptions(Request $request)
    {
        try {
            $years = RevenueAccountData::select('year')
                ->whereNotNull('year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear))->sortDesc()->values();
            }

            $accounts = AccountNumber::orderBy('account_number')
                ->select('id', 'account_number', 'description')
                ->get();

            $estimates = Estimate::select('id', 'revenue_code_name', 'head', 'project', 'object')
                ->whereNotNull('revenue_code_name')
                ->orderBy('revenue_code_name')
                ->get()
                ->map(function ($estimate) {
                    return [
                        'id' => $estimate->id,
                        'revenue_code_name' => $estimate->revenue_code_name,
                        'head' => $estimate->head,
                        'project' => $estimate->project,
                        'object' => $estimate->object,
                        'display' => $this->formatEstimateDisplay($estimate)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                    'accounts' => $accounts,
                    'estimates' => $estimates
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in MoneyTransists getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [
                    'years' => [],
                    'accounts' => [],
                    'estimates' => []
                ]
            ]);
        }
    }

   

     /**
     * Export data to CSV
     */
    public function export(Request $request)
    {
        try {
            $year = $request->input('year');
            $accountNumberId = $request->input('account_number_id');
            $estimateId = $request->input('estimate_id');

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            $dataResponse = $this->getData($request);
            $data = json_decode($dataResponse->getContent(), true);

            if (!$data['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data for export'
                ], 500);
            }

            $records = $data['data']['records'];
            $monthNames = $data['data']['month_names'];
            $months = $data['data']['months'];
            $grandTotals = $data['data']['grand_totals'];

            // Get selected account and estimate details
            $accountNumberId = $request->input('account_number_id');
            $estimateId = $request->input('estimate_id');
            
            // Get account and estimate names
            $accountNumber = null;
            $accountDescription = null;
            if ($accountNumberId) {
                $account = AccountNumber::find($accountNumberId);
                if ($account) {
                    $accountNumber = $account->account_number;
                    $accountDescription = $account->description;
                }
            }
            
            $estimateDisplay = null;
            if ($estimateId) {
                $estimate = Estimate::find($estimateId);
                if ($estimate) {
                    $estimateDisplay = $this->formatEstimateDisplay($estimate);
                }
            }

            // Aggregate monthly data across all accounts
            $monthlyData = [];
            foreach ($months as $monthNum) {
                $monthlyData[$monthNum] = [
                    'opening_balance' => 0,
                    'revenue_collection' => 0,
                    'receipts' => 0,
                    'transists' => 0
                ];
            }

            // If a specific account is selected, use that account's data
            if ($accountNumberId) {
                $accountRecord = null;
                foreach ($records as $record) {
                    if ($record['account_number_id'] == $accountNumberId) {
                        $accountRecord = $record;
                        break;
                    }
                }
                
                if ($accountRecord) {
                    foreach ($months as $monthNum) {
                        $data = $accountRecord['monthly_data'][$monthNum] ?? [
                            'opening_balance' => 0,
                            'revenue_collection' => 0,
                            'receipts' => 0,
                            'transists' => 0
                        ];
                        $monthlyData[$monthNum]['opening_balance'] = $data['opening_balance'] ?? 0;
                        $monthlyData[$monthNum]['revenue_collection'] = $data['revenue_collection'] ?? 0;
                        $monthlyData[$monthNum]['receipts'] = $data['receipts'] ?? 0;
                        $monthlyData[$monthNum]['transists'] = $data['transists'] ?? 0;
                    }
                }
            } else {
                // Aggregate across all accounts
                foreach ($records as $record) {
                    foreach ($months as $monthNum) {
                        $data = $record['monthly_data'][$monthNum] ?? [
                            'opening_balance' => 0,
                            'revenue_collection' => 0,
                            'receipts' => 0,
                            'transists' => 0
                        ];
                        $monthlyData[$monthNum]['opening_balance'] += $data['opening_balance'] ?? 0;
                        $monthlyData[$monthNum]['revenue_collection'] += $data['revenue_collection'] ?? 0;
                        $monthlyData[$monthNum]['receipts'] += $data['receipts'] ?? 0;
                        $monthlyData[$monthNum]['transists'] += $data['transists'] ?? 0;
                    }
                }
            }

            // Build CSV with months as rows
            $csvRows = [];
            
            // Add report header information
            $csvRows[] = '"Money Transists Report"';
            $csvRows[] = '"Year: ' . $year . '"';
            if ($accountNumber) {
                $csvRows[] = '"Account: ' . $accountNumber . ($accountDescription ? ' - ' . $accountDescription : '') . '"';
            } else {
                $csvRows[] = '"Account: All Accounts"';
            }
            if ($estimateDisplay) {
                $csvRows[] = '"Estimate: ' . $estimateDisplay . '"';
            } else {
                $csvRows[] = '"Estimate: All Estimates"';
            }
            $csvRows[] = '"Generated on: ' . date('Y-m-d H:i:s') . '"';
            $csvRows[] = ''; // Empty row for spacing

            // CSV Headers - Months as rows
            $headers = ['Month', 'Opening Balance', 'Revenue Collection', 'Receipts', 'Transists'];
            $csvRows[] = implode(',', array_map(function($h) { 
                return '"' . $h . '"'; 
            }, $headers));

            // Format number helper - WITHOUT commas
            $formatNumber = function($value) {
                if ($value === null || $value === '') return '0.00';
                return number_format((float)$value, 2, '.', '');
            };

            // Add data rows - each month as a row
            foreach ($months as $monthNum) {
                $data = $monthlyData[$monthNum] ?? [
                    'opening_balance' => 0,
                    'revenue_collection' => 0,
                    'receipts' => 0,
                    'transists' => 0
                ];
                
                $row = [
                    '"' . $monthNames[$monthNum] . '"',
                    $formatNumber($data['opening_balance']),
                    $formatNumber($data['revenue_collection']),
                    $formatNumber($data['receipts']),
                    $formatNumber($data['transists'])
                ];
                $csvRows[] = implode(',', $row);
            }

            // Calculate grand totals
            $totalOpening = 0;
            $totalCollection = 0;
            $totalReceipts = 0;
            $totalTransists = 0;
            
            foreach ($months as $monthNum) {
                $data = $monthlyData[$monthNum] ?? [
                    'opening_balance' => 0,
                    'revenue_collection' => 0,
                    'receipts' => 0,
                    'transists' => 0
                ];
                $totalOpening += $data['opening_balance'] ?? 0;
                $totalCollection += $data['revenue_collection'] ?? 0;
                $totalReceipts += $data['receipts'] ?? 0;
                $totalTransists += $data['transists'] ?? 0;
            }

            // Grand total row
            $grandTotalRow = [
                '"GRAND TOTAL"',
                $formatNumber($totalOpening),
                $formatNumber($totalCollection),
                $formatNumber($totalReceipts),
                $formatNumber($totalTransists)
            ];
            $csvRows[] = implode(',', $grandTotalRow);

            // Add footer
            $csvRows[] = ''; // Empty row
            $csvRows[] = '"--- End of Report ---"';

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            // Add BOM for UTF-8 Excel compatibility
            $csvContent = "\xEF\xBB\xBF" . $csvContent;

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=money_transists_{$year}.csv")
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Error in MoneyTransists export: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to PDF
     */
    public function exportPDF(Request $request)
    {
        try {
            $year = $request->input('year');
            $accountNumberId = $request->input('account_number_id');
            $estimateId = $request->input('estimate_id');

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            $dataResponse = $this->getData($request);
            $data = json_decode($dataResponse->getContent(), true);

            if (!$data['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data for export'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $data['data'],
                'year' => $year
            ]);

        } catch (\Exception $e) {
            Log::error('Error in MoneyTransists exportPDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}