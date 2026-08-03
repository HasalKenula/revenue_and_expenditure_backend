<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RevenueAccountData;
use App\Models\AccountNumber;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueReceiptsInCashController extends Controller
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
        $object = $this->padNumber($estimate->object, 3);
        
        return "{$head}-{$project}-{$object}";
    }

    /**
     * Get Revenue Receipts In Cash Report Data
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
                        'grand_totals' => [],
                        'grand_total_overall' => 0,
                        'year' => $year,
                        'month_names' => [],
                        'months' => [],
                        'total_records' => 0
                    ]
                ]);
            }

            \Log::info('RevenueReceiptsInCash getData called', ['year' => $year, 'selected_accounts' => $selectedAccounts]);

            $months = range(1, 12);
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
            $allOtherTotals = array_fill(1, 12, 0);
            $allOtherTotalAmount = 0;
            $grandTotals = array_fill(1, 12, 0);
            $grandTotalOverall = 0;

            foreach ($accounts as $account) {
                $revenueData = RevenueAccountData::where('account_number_id', $account->id)
                    ->whereYear('created_at', $year)
                    ->get();

                $monthlyTotals = array_fill(1, 12, 0);
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

                    if ($monthNum !== null && isset($monthlyTotals[$monthNum])) {
                        $monthlyTotals[$monthNum] += (float)$data->amount;
                        $totalAmount += (float)$data->amount;
                    }
                }

                if ($totalAmount > 0) {
                    $row = [
                        'account_number_id' => $account->id,
                        'account_number' => $account->account_number,
                        'account_description' => $account->description ?? '-',
                        'monthly_totals' => $monthlyTotals,
                        'total' => $totalAmount,
                        'is_other' => false
                    ];

                    $results[] = $row;

                    foreach ($months as $monthNum) {
                        $grandTotals[$monthNum] += $monthlyTotals[$monthNum];
                    }
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

                    if ($monthNum !== null && isset($allOtherTotals[$monthNum])) {
                        $allOtherTotals[$monthNum] += (float)$data->amount;
                        $allOtherTotalAmount += (float)$data->amount;
                    }
                }
            }

            if ($allOtherTotalAmount > 0) {
                $results[] = [
                    'account_number_id' => null,
                    'account_number' => 'Repop & Fixed',
                    'account_description' => 'All Other Accounts',
                    'monthly_totals' => $allOtherTotals,
                    'total' => $allOtherTotalAmount,
                    'is_other' => true
                ];

                foreach ($months as $monthNum) {
                    $grandTotals[$monthNum] += $allOtherTotals[$monthNum];
                }
                $grandTotalOverall += $allOtherTotalAmount;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'grand_totals' => $grandTotals,
                    'grand_total_overall' => $grandTotalOverall,
                    'year' => $year,
                    'month_names' => $monthNames,
                    'months' => $months,
                    'total_records' => count($results),
                    'selected_accounts' => $selectedAccounts
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in RevenueReceiptsInCash getData: ' . $e->getMessage());
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
            \Log::error('Error in RevenueReceiptsInCash getFilterOptions: ' . $e->getMessage());
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
     * Export data to CSV - Direct Download
     */
    public function export(Request $request)
    {
        try {
            $year = $request->input('year');
            $selectedAccounts = $request->input('selected_accounts', []);

            \Log::info('RevenueReceiptsInCash export called', ['year' => $year, 'selected_accounts' => $selectedAccounts]);

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

            $months = range(1, 12);
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            $accounts = AccountNumber::whereIn('account_number', $selectedAccounts)
                ->orderBy('account_number')
                ->get();

            $allOtherTotals = array_fill(1, 12, 0);
            $allOtherTotalAmount = 0;
            $grandTotals = array_fill(1, 12, 0);
            $grandTotalOverall = 0;

            // Build CSV content
            $csvContent = '';
            
            // Add headers
            $headers = ['Account Number'];
            foreach ($months as $monthNum) {
                $headers[] = $monthNames[$monthNum];
            }
            $headers[] = 'Total';
            $csvContent .= implode(',', $headers) . "\n";

            foreach ($accounts as $account) {
                $revenueData = RevenueAccountData::where('account_number_id', $account->id)
                    ->whereYear('created_at', $year)
                    ->get();

                $monthlyTotals = array_fill(1, 12, 0);
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

                    if ($monthNum !== null && isset($monthlyTotals[$monthNum])) {
                        $monthlyTotals[$monthNum] += (float)$data->amount;
                        $totalAmount += (float)$data->amount;
                    }
                }

                if ($totalAmount > 0) {
                    $row = [$account->account_number];
                    foreach ($months as $monthNum) {
                        $row[] = round($monthlyTotals[$monthNum] ?? 0, 2);
                    }
                    $row[] = round($totalAmount, 2);
                    $csvContent .= implode(',', $row) . "\n";

                    foreach ($months as $monthNum) {
                        $grandTotals[$monthNum] += $monthlyTotals[$monthNum];
                    }
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

                    if ($monthNum !== null && isset($allOtherTotals[$monthNum])) {
                        $allOtherTotals[$monthNum] += (float)$data->amount;
                        $allOtherTotalAmount += (float)$data->amount;
                    }
                }
            }

            if ($allOtherTotalAmount > 0) {
                $row = ['Repop & Fixed'];
                foreach ($months as $monthNum) {
                    $row[] = round($allOtherTotals[$monthNum] ?? 0, 2);
                }
                $row[] = round($allOtherTotalAmount, 2);
                $csvContent .= implode(',', $row) . "\n";

                foreach ($months as $monthNum) {
                    $grandTotals[$monthNum] += $allOtherTotals[$monthNum];
                }
                $grandTotalOverall += $allOtherTotalAmount;
            }

            // Add grand total row
            $grandTotalRow = ['GRAND TOTAL'];
            foreach ($months as $monthNum) {
                $grandTotalRow[] = round($grandTotals[$monthNum] ?? 0, 2);
            }
            $grandTotalRow[] = round($grandTotalOverall, 2);
            $csvContent .= implode(',', $grandTotalRow) . "\n";

            // Return as downloadable CSV
            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=revenue_receipts_in_cash_{$year}.csv")
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            \Log::error('Error in RevenueReceiptsInCash export: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}