<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RevenueAccountData;
use App\Models\AccountNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueReceiptsInCashSummaryController extends Controller
{
    /**
     * Get Revenue Receipts In Cash Summary Report Data
     * Returns only Account Number and Total (sum of all 12 months)
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            $selectedAccounts = $request->input('selected_accounts', []);
            
            if (is_string($selectedAccounts)) {
                $selectedAccounts = json_decode($selectedAccounts, true);
                if (!is_array($selectedAccounts)) {
                    $selectedAccounts = [];
                }
            }
            
            if (empty($selectedAccounts)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'records' => [],
                        'grand_total_overall' => 0,
                        'year' => $year,
                        'total_records' => 0
                    ]
                ]);
            }

            \Log::info('RevenueReceiptsInCashSummary getData called', ['year' => $year, 'selected_accounts' => $selectedAccounts]);

            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            $accounts = AccountNumber::whereIn('account_number', $selectedAccounts)
                ->orderBy('account_number')
                ->get();

            $results = [];
            $allOtherTotal = 0;
            $grandTotalOverall = 0;

            foreach ($accounts as $account) {
                $revenueData = RevenueAccountData::where('account_number_id', $account->id)
                    ->whereYear('created_at', $year)
                    ->get();

                $totalAmount = 0;

                foreach ($revenueData as $data) {
                    $monthStr = $data->month;
                    $monthNum = null;
                    
                    foreach ($monthNames as $num => $name) {
                        if (strpos($monthStr, $name) !== false) {
                            $monthNum = $num;
                            break;
                        }
                    }

                    if ($monthNum !== null) {
                        $totalAmount += (float)$data->amount;
                    }
                }

                if ($totalAmount > 0) {
                    $results[] = [
                        'account_number' => $account->account_number,
                        'total' => $totalAmount
                    ];
                    $grandTotalOverall += $totalAmount;
                }
            }

            // Get "Repop & Fixed" - all other accounts not selected
            $allAccounts = AccountNumber::whereNotIn('account_number', $selectedAccounts)
                ->orderBy('account_number')
                ->get();

            foreach ($allAccounts as $account) {
                $revenueData = RevenueAccountData::where('account_number_id', $account->id)
                    ->whereYear('created_at', $year)
                    ->get();

                foreach ($revenueData as $data) {
                    $monthStr = $data->month;
                    $monthNum = null;
                    
                    foreach ($monthNames as $num => $name) {
                        if (strpos($monthStr, $name) !== false) {
                            $monthNum = $num;
                            break;
                        }
                    }

                    if ($monthNum !== null) {
                        $allOtherTotal += (float)$data->amount;
                    }
                }
            }

            if ($allOtherTotal > 0) {
                $results[] = [
                    'account_number' => 'Repop & Fixed',
                    'total' => $allOtherTotal
                ];
                $grandTotalOverall += $allOtherTotal;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'grand_total_overall' => $grandTotalOverall,
                    'year' => $year,
                    'total_records' => count($results),
                    'selected_accounts' => $selectedAccounts
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in RevenueReceiptsInCashSummary getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options endpoint
     */
    public function getFilterOptions(Request $request)
    {
        try {
            $years = RevenueAccountData::select(DB::raw('DISTINCT YEAR(created_at) as year'))
                ->whereNotNull('created_at')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear))->sortDesc()->values();
            }

            $accounts = AccountNumber::orderBy('account_number')
                ->pluck('account_number')
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                    'accounts' => $accounts
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in RevenueReceiptsInCashSummary getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [
                    'years' => [],
                    'accounts' => []
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
            $selectedAccounts = $request->input('selected_accounts', []);

            \Log::info('RevenueReceiptsInCashSummary export called', ['year' => $year, 'selected_accounts' => $selectedAccounts]);

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            if (is_string($selectedAccounts)) {
                $selectedAccounts = json_decode($selectedAccounts, true);
                if (!is_array($selectedAccounts)) {
                    $selectedAccounts = [];
                }
            }

            if (empty($selectedAccounts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select at least one account'
                ], 422);
            }

            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            $accounts = AccountNumber::whereIn('account_number', $selectedAccounts)
                ->orderBy('account_number')
                ->get();

            $allOtherTotal = 0;
            $grandTotalOverall = 0;

            // Build CSV content
            $csvContent = '';
            
            // Add headers
            $headers = ['Account Number', 'Total'];
            $csvContent .= implode(',', $headers) . "\n";

            foreach ($accounts as $account) {
                $revenueData = RevenueAccountData::where('account_number_id', $account->id)
                    ->whereYear('created_at', $year)
                    ->get();

                $totalAmount = 0;

                foreach ($revenueData as $data) {
                    $monthStr = $data->month;
                    $monthNum = null;
                    
                    foreach ($monthNames as $num => $name) {
                        if (strpos($monthStr, $name) !== false) {
                            $monthNum = $num;
                            break;
                        }
                    }

                    if ($monthNum !== null) {
                        $totalAmount += (float)$data->amount;
                    }
                }

                if ($totalAmount > 0) {
                    $row = [$account->account_number, round($totalAmount, 2)];
                    $csvContent .= implode(',', $row) . "\n";
                    $grandTotalOverall += $totalAmount;
                }
            }

            // Get "Repop & Fixed"
            $allAccounts = AccountNumber::whereNotIn('account_number', $selectedAccounts)
                ->orderBy('account_number')
                ->get();

            foreach ($allAccounts as $account) {
                $revenueData = RevenueAccountData::where('account_number_id', $account->id)
                    ->whereYear('created_at', $year)
                    ->get();

                foreach ($revenueData as $data) {
                    $monthStr = $data->month;
                    $monthNum = null;
                    
                    foreach ($monthNames as $num => $name) {
                        if (strpos($monthStr, $name) !== false) {
                            $monthNum = $num;
                            break;
                        }
                    }

                    if ($monthNum !== null) {
                        $allOtherTotal += (float)$data->amount;
                    }
                }
            }

            if ($allOtherTotal > 0) {
                $row = ['Repop & Fixed', round($allOtherTotal, 2)];
                $csvContent .= implode(',', $row) . "\n";
                $grandTotalOverall += $allOtherTotal;
            }

            // Add grand total row
            $grandTotalRow = ['GRAND TOTAL', round($grandTotalOverall, 2)];
            $csvContent .= implode(',', $grandTotalRow) . "\n";

            // Return as downloadable CSV
            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=revenue_receipts_in_cash_summary_{$year}.csv")
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            \Log::error('Error in RevenueReceiptsInCashSummary export: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}