<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RevenueAccountData;
use App\Models\AccountNumber;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueCollectionAccountNumberController extends Controller
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

        // Always show all three parts, even if they are null/undefined
        $head = $this->padNumber($estimate->head, 4);
        $project = $this->padNumber($estimate->project, 2);
        $object = $this->padNumber($estimate->object, 2);
        
        return "{$head}-{$project}-{$object}";
    }

    /**
     * Get Revenue Collection by Account Number Report Data
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

            \Log::info('RevenueCollectionAccountNumber getData called', ['year' => $year]);

            // Get all months (January to December)
            $months = range(1, 12);
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Get all account numbers
            $accounts = AccountNumber::orderBy('account_number')->get();
            
            \Log::info('Accounts found', ['count' => $accounts->count()]);

            $results = [];

            foreach ($accounts as $account) {
                // Get all revenue data for this account for the selected year using created_at
                $revenueData = RevenueAccountData::where('account_number_id', $account->id)
                    ->whereYear('created_at', $year)
                    ->get();

                // Group by revenue code (estimate_id)
                $groupedByRevenueCode = [];
                
                foreach ($revenueData as $data) {
                    $estimate = null;
                    $revenueCode = '-';
                    
                    if ($data->estimate_id) {
                        $estimate = Estimate::find($data->estimate_id);
                        if ($estimate) {
                            $revenueCode = $this->formatRevenueCode($estimate);
                        }
                    }

                    $key = $revenueCode;

                    if (!isset($groupedByRevenueCode[$key])) {
                        $groupedByRevenueCode[$key] = [
                            'revenue_code' => $revenueCode,
                            'monthly_totals' => array_fill(1, 12, 0),
                            'total' => 0
                        ];
                    }

                    // Get month from the month column
                    $monthStr = $data->month;
                    $monthNum = null;
                    
                    foreach ($monthNames as $num => $name) {
                        if (strpos($monthStr, $name) !== false) {
                            $monthNum = $num;
                            break;
                        }
                    }

                    if ($monthNum !== null && isset($groupedByRevenueCode[$key]['monthly_totals'][$monthNum])) {
                        $groupedByRevenueCode[$key]['monthly_totals'][$monthNum] += $data->amount;
                        $groupedByRevenueCode[$key]['total'] += $data->amount;
                    }
                }

                // Create a separate row for each revenue code
                foreach ($groupedByRevenueCode as $revenueCode => $data) {
                    // Only include if total > 0
                    if ($data['total'] > 0) {
                        $row = [
                            'account_number_id' => $account->id,
                            'account_number' => $account->account_number,
                            'revenue_code' => $revenueCode,
                            'account_description' => $account->description ?? '-',
                            'monthly_totals' => $data['monthly_totals'],
                            'total' => $data['total'],
                            'has_data' => true
                        ];

                        $results[] = $row;
                    }
                }
            }

            // Sort results by account number and then by revenue code
            usort($results, function($a, $b) {
                $accountCompare = strcmp($a['account_number'], $b['account_number']);
                if ($accountCompare !== 0) {
                    return $accountCompare;
                }
                return strcmp($a['revenue_code'], $b['revenue_code']);
            });

            // Calculate grand totals for each month
            $grandTotals = [];
            foreach ($months as $monthNum) {
                $grandTotals[$monthNum] = 0;
                foreach ($results as $row) {
                    $grandTotals[$monthNum] += $row['monthly_totals'][$monthNum] ?? 0;
                }
            }

            $grandTotalOverall = array_sum($grandTotals);

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'grand_totals' => $grandTotals,
                    'grand_total_overall' => $grandTotalOverall,
                    'year' => $year,
                    'month_names' => $monthNames,
                    'months' => $months,
                    'total_accounts' => count($results),
                    'accounts_with_data' => count($results),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in RevenueCollectionAccountNumber getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get filter options endpoint - handles both GET requests with query params
     */
    public function getFilterOptionsEndpoint(Request $request)
    {
        try {
            // Get available years from revenue_account_data created_at
            $years = RevenueAccountData::select(DB::raw('DISTINCT YEAR(created_at) as year'))
                ->whereNotNull('created_at')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            // If no years found, provide default range
            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear))->sortDesc()->values();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in RevenueCollectionAccountNumber getFilterOptionsEndpoint: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [
                    'years' => [],
                ]
            ]);
        }
    }

    /**
     * Export data to Excel/CSV
     */
    public function export(Request $request)
    {
        try {
            $year = $request->input('year');

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            // Get all months (January to December)
            $months = range(1, 12);
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Get all account numbers
            $accounts = AccountNumber::orderBy('account_number')->get();

            $exportData = [];

            foreach ($accounts as $account) {
                // Get all revenue data for this account for the selected year using created_at
                $revenueData = RevenueAccountData::where('account_number_id', $account->id)
                    ->whereYear('created_at', $year)
                    ->get();

                // Group by revenue code (estimate_id)
                $groupedByRevenueCode = [];
                
                foreach ($revenueData as $data) {
                    $estimate = null;
                    $revenueCode = '-';
                    
                    if ($data->estimate_id) {
                        $estimate = Estimate::find($data->estimate_id);
                        if ($estimate) {
                            $revenueCode = $this->formatRevenueCode($estimate);
                        }
                    }

                    $key = $revenueCode;

                    if (!isset($groupedByRevenueCode[$key])) {
                        $groupedByRevenueCode[$key] = [
                            'revenue_code' => $revenueCode,
                            'monthly_totals' => array_fill(1, 12, 0),
                            'total' => 0
                        ];
                    }

                    // Get month from the month column
                    $monthStr = $data->month;
                    $monthNum = null;
                    
                    foreach ($monthNames as $num => $name) {
                        if (strpos($monthStr, $name) !== false) {
                            $monthNum = $num;
                            break;
                        }
                    }

                    if ($monthNum !== null && isset($groupedByRevenueCode[$key]['monthly_totals'][$monthNum])) {
                        $groupedByRevenueCode[$key]['monthly_totals'][$monthNum] += $data->amount;
                        $groupedByRevenueCode[$key]['total'] += $data->amount;
                    }
                }

                // Create a separate row for each revenue code
                foreach ($groupedByRevenueCode as $revenueCode => $data) {
                    // Only include if total > 0
                    if ($data['total'] > 0) {
                        $row = [
                            'Account Number' => $account->account_number,
                            'Revenue Code' => $revenueCode,
                        ];

                        // Add monthly columns
                        foreach ($months as $monthNum) {
                            $row[$monthNames[$monthNum]] = round($data['monthly_totals'][$monthNum] ?? 0, 2);
                        }

                        $row['Total'] = round($data['total'], 2);
                        $exportData[] = $row;
                    }
                }
            }

            // Sort by account number then revenue code
            usort($exportData, function($a, $b) {
                $accountCompare = strcmp($a['Account Number'], $b['Account Number']);
                if ($accountCompare !== 0) {
                    return $accountCompare;
                }
                return strcmp($a['Revenue Code'], $b['Revenue Code']);
            });

            // Calculate grand totals
            $grandTotals = [];
            foreach ($months as $monthNum) {
                $grandTotals[$monthNum] = 0;
                foreach ($exportData as $row) {
                    $grandTotals[$monthNum] += $row[$monthNames[$monthNum]] ?? 0;
                }
            }

            // Add grand total row
            if (!empty($exportData)) {
                $grandTotalRow = [
                    'Account Number' => 'GRAND TOTAL',
                    'Revenue Code' => '',
                ];

                foreach ($months as $monthNum) {
                    $grandTotalRow[$monthNames[$monthNum]] = round($grandTotals[$monthNum] ?? 0, 2);
                }

                $grandTotalRow['Total'] = round(array_sum($grandTotals), 2);
                $exportData[] = $grandTotalRow;
            }

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'total_records' => count($exportData),
                'year' => $year
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in RevenueCollectionAccountNumber export: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}