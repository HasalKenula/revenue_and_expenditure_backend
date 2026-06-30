<?php

// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use App\Models\MonthlyFincance;
// use App\Models\OpeningBalance;
// use App\Models\ImpressIssue;
// use App\Models\ImpressSettlement;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;

// class ImprestBalanceController extends Controller
// {
//     /**
//      * Get Imprest Balance data
//      */
//     public function getData(Request $request)
//     {
//         try {
//             $year = $request->input('year');
//             $month = $request->input('month');
//             $trno = $request->input('trno');
            
//             \Log::info('ImprestBalance getData called', [
//                 'year' => $year,
//                 'month' => $month,
//                 'trno' => $trno
//             ]);

//             // Validate year and month
//             if (!$year) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Year is required'
//                 ], 422);
//             }

//             if (!$month || $month < 1 || $month > 12) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Valid month is required (1-12)'
//                 ], 422);
//             }

//             // Get all distinct TRNOs from monthly_fincances for the selected year and month
//             $query = MonthlyFincance::whereYear('created_at', $year)
//                 ->where('month', $month);
            
//             if ($trno) {
//                 $query->where('trno', $trno);
//             }

//             $trnos = $query->distinct()
//                 ->orderBy('trno')
//                 ->pluck('trno')
//                 ->values();

//             if ($trnos->isEmpty()) {
//                 return response()->json([
//                     'success' => true,
//                     'data' => [],
//                     'message' => 'No records found for the selected filters'
//                 ]);
//             }

//             $results = [];
//             $grandTotalOpeningBalance = 0;
//             $grandTotalDR = 0;
//             $grandTotalIssue = 0;
//             $grandTotalCR = 0;
//             $grandTotalSettle = 0;
//             $grandTotalBalance = 0;

//             foreach ($trnos as $currentTrno) {
//                 // Get Opening Balance
//                 $openingBalance = OpeningBalance::where('head', $currentTrno)
//                     ->where('year', $year)
//                     ->first();

//                 $openingBalanceAmount = $openingBalance ? floatval($openingBalance->opening_balance) : 0;

//                 // Get DR Amount - dr_cr_code = 7002, dr_cr = DR
//                 $drAmount = MonthlyFincance::whereYear('created_at', $year)
//                     ->where('month', $month)
//                     ->where('trno', $currentTrno)
//                     ->where('dr_cr_code', 7002)
//                     ->where('dr_cr', 'DR')
//                     ->sum('cash_xe');

//                 // Get Issue Amount from impress_issues
//                 $issueAmount = ImpressIssue::where('head', $currentTrno)
//                     ->where('year', $year)
//                     ->where('month', $month)
//                     ->sum('amount');

//                 // Get CR Amount - dr_cr_code = 7002, dr_cr = CR
//                 $crAmount = MonthlyFincance::whereYear('created_at', $year)
//                     ->where('month', $month)
//                     ->where('trno', $currentTrno)
//                     ->where('dr_cr_code', 7002)
//                     ->where('dr_cr', 'CR')
//                     ->sum('cash_xe');

//                 // Get Settle Amount from impress_settlements
//                 $settleAmount = ImpressSettlement::where('head', $currentTrno)
//                     ->where('year', $year)
//                     ->where('month', $month)
//                     ->sum('amount');

//                 // Calculate Grand Total
//                 $grandTotal = $openingBalanceAmount + $drAmount + $issueAmount - $crAmount - $settleAmount;

//                 $results[] = [
//                     'trno' => $currentTrno,
//                     'opening_balance' => round($openingBalanceAmount, 2),
//                     'dr_amount' => round($drAmount, 2),
//                     'issue_amount' => round($issueAmount, 2),
//                     'cr_amount' => round($crAmount, 2),
//                     'settle_amount' => round($settleAmount, 2),
//                     'grand_total' => round($grandTotal, 2),
//                 ];

//                 $grandTotalOpeningBalance += $openingBalanceAmount;
//                 $grandTotalDR += $drAmount;
//                 $grandTotalIssue += $issueAmount;
//                 $grandTotalCR += $crAmount;
//                 $grandTotalSettle += $settleAmount;
//                 $grandTotalBalance += $grandTotal;
//             }

//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'records' => $results,
//                     'grand_totals' => [
//                         'total_opening_balance' => round($grandTotalOpeningBalance, 2),
//                         'total_dr' => round($grandTotalDR, 2),
//                         'total_issue' => round($grandTotalIssue, 2),
//                         'total_cr' => round($grandTotalCR, 2),
//                         'total_settle' => round($grandTotalSettle, 2),
//                         'total_grand' => round($grandTotalBalance, 2),
//                     ],
//                     'filters' => [
//                         'year' => $year,
//                         'month' => $month,
//                         'trno' => $trno,
//                     ]
//                 ]
//             ]);

//         } catch (\Exception $e) {
//             \Log::error('Error in ImprestBalance getData: ' . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage(),
//                 'line' => $e->getLine()
//             ], 500);
//         }
//     }

//     /**
//      * Get filter options (years, months, and TRNOs)
//      */
//     public function getFilterOptions(Request $request)
//     {
//         try {
//             $year = $request->input('year');
//             $month = $request->input('month');

//             // Get available years
//             $years = MonthlyFincance::select(DB::raw('YEAR(created_at) as year'))
//                 ->distinct()
//                 ->orderBy('year', 'desc')
//                 ->pluck('year')
//                 ->values();

//             if ($years->isEmpty()) {
//                 $currentYear = date('Y');
//                 $years = collect(range($currentYear - 5, $currentYear));
//             }

//             // Months 1-12
//             $months = collect(range(1, 12));

//             // Get TRNOs based on year and month
//             $trnosQuery = MonthlyFincance::whereNotNull('trno');
            
//             if ($year) {
//                 $trnosQuery->whereYear('created_at', $year);
//             }
//             if ($month) {
//                 $trnosQuery->where('month', $month);
//             }
            
//             $trnos = $trnosQuery->distinct()
//                 ->orderBy('trno')
//                 ->pluck('trno')
//                 ->values();

//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'years' => $years,
//                     'months' => $months,
//                     'trnos' => $trnos,
//                 ]
//             ]);
//         } catch (\Exception $e) {
//             \Log::error('Error in ImprestBalance getFilterOptions: ' . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * Export data to CSV
//      */
//     public function export(Request $request)
//     {
//         try {
//             $year = $request->input('year');
//             $month = $request->input('month');
//             $trno = $request->input('trno');

//             if (!$year || !$month) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Year and month are required'
//                 ], 422);
//             }

//             // Get data using the same logic
//             $query = MonthlyFincance::whereYear('created_at', $year)
//                 ->where('month', $month);
            
//             if ($trno) {
//                 $query->where('trno', $trno);
//             }

//             $trnos = $query->distinct()
//                 ->orderBy('trno')
//                 ->pluck('trno')
//                 ->values();

//             if ($trnos->isEmpty()) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'No data to export'
//                 ], 404);
//             }

//             $exportData = [];
//             $grandTotalOpeningBalance = 0;
//             $grandTotalDR = 0;
//             $grandTotalIssue = 0;
//             $grandTotalCR = 0;
//             $grandTotalSettle = 0;
//             $grandTotalBalance = 0;

//             foreach ($trnos as $currentTrno) {
//                 $openingBalance = OpeningBalance::where('head', $currentTrno)
//                     ->where('year', $year)
//                     ->first();

//                 $openingBalanceAmount = $openingBalance ? floatval($openingBalance->opening_balance) : 0;

//                 $drAmount = MonthlyFincance::whereYear('created_at', $year)
//                     ->where('month', $month)
//                     ->where('trno', $currentTrno)
//                     ->where('dr_cr_code', 7002)
//                     ->where('dr_cr', 'DR')
//                     ->sum('cash_xe');

//                 $issueAmount = ImpressIssue::where('head', $currentTrno)
//                     ->where('year', $year)
//                     ->where('month', $month)
//                     ->sum('amount');

//                 $crAmount = MonthlyFincance::whereYear('created_at', $year)
//                     ->where('month', $month)
//                     ->where('trno', $currentTrno)
//                     ->where('dr_cr_code', 7002)
//                     ->where('dr_cr', 'CR')
//                     ->sum('cash_xe');

//                 $settleAmount = ImpressSettlement::where('head', $currentTrno)
//                     ->where('year', $year)
//                     ->where('month', $month)
//                     ->sum('amount');

//                 $grandTotal = $openingBalanceAmount + $drAmount + $issueAmount - $crAmount - $settleAmount;

//                 $exportData[] = [
//                     'TR No' => $currentTrno,
//                     'Opening Balance' => round($openingBalanceAmount, 2),
//                     'DR Amount' => round($drAmount, 2),
//                     'Issue Amount' => round($issueAmount, 2),
//                     'CR Amount' => round($crAmount, 2),
//                     'Settle Amount' => round($settleAmount, 2),
//                     'Grand Total' => round($grandTotal, 2),
//                 ];

//                 $grandTotalOpeningBalance += $openingBalanceAmount;
//                 $grandTotalDR += $drAmount;
//                 $grandTotalIssue += $issueAmount;
//                 $grandTotalCR += $crAmount;
//                 $grandTotalSettle += $settleAmount;
//                 $grandTotalBalance += $grandTotal;
//             }

//             // Add grand total row
//             $exportData[] = [
//                 'TR No' => 'GRAND TOTAL',
//                 'Opening Balance' => round($grandTotalOpeningBalance, 2),
//                 'DR Amount' => round($grandTotalDR, 2),
//                 'Issue Amount' => round($grandTotalIssue, 2),
//                 'CR Amount' => round($grandTotalCR, 2),
//                 'Settle Amount' => round($grandTotalSettle, 2),
//                 'Grand Total' => round($grandTotalBalance, 2),
//             ];

//             return response()->json([
//                 'success' => true,
//                 'data' => $exportData,
//                 'total_records' => count($exportData)
//             ]);

//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
// }





namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use App\Models\OpeningBalance;
use App\Models\ImpressIssue;
use App\Models\ImpressSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImprestBalanceController extends Controller
{
    /**
     * Get Imprest Balance data with cumulative values
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $trno = $request->input('trno');
            $viewType = $request->input('view_type', 'cumulative'); // 'cumulative' or 'monthly'
            
            \Log::info('ImprestBalance getData called', [
                'year' => $year,
                'month' => $month,
                'trno' => $trno,
                'view_type' => $viewType
            ]);

            // Validate year and month
            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            if (!$month || $month < 1 || $month > 12) {
                return response()->json([
                    'success' => false,
                    'message' => 'Valid month is required (1-12)'
                ], 422);
            }

            // Determine which months to show
            if ($viewType === 'cumulative') {
                $monthsToShow = range(1, (int)$month);
            } else {
                $monthsToShow = [(int)$month];
            }

            // Get all distinct TRNOs
            $trnosQuery = MonthlyFincance::whereYear('created_at', $year)
                ->whereIn('month', $monthsToShow);
            
            if ($trno) {
                $trnosQuery->where('trno', $trno);
            }

            $trnos = $trnosQuery->distinct()
                ->orderBy('trno')
                ->pluck('trno')
                ->values();

            if ($trnos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No records found for the selected filters'
                ]);
            }

            $results = [];
            $grandTotalOpeningBalance = 0;
            $grandTotalDR = 0;
            $grandTotalIssue = 0;
            $grandTotalCR = 0;
            $grandTotalSettle = 0;
            $grandTotalBalance = 0;

            foreach ($trnos as $currentTrno) {
                // Get Opening Balance (only once, not cumulative)
                $openingBalance = OpeningBalance::where('head', $currentTrno)
                    ->where('year', $year)
                    ->first();

                $openingBalanceAmount = $openingBalance ? floatval($openingBalance->opening_balance) : 0;

                // Initialize cumulative values
                $cumulativeDR = 0;
                $cumulativeIssue = 0;
                $cumulativeCR = 0;
                $cumulativeSettle = 0;

                // For each month up to selected month
                foreach ($monthsToShow as $currentMonth) {
                    // Get DR Amount - cumulative
                    $drAmount = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', $currentTrno)
                        ->where('dr_cr_code', 7002)
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    // Get Issue Amount - cumulative
                    $issueAmount = ImpressIssue::where('head', $currentTrno)
                        ->where('year', $year)
                        ->where('month', $currentMonth)
                        ->sum('amount');

                    // Get CR Amount - cumulative
                    $crAmount = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', $currentTrno)
                        ->where('dr_cr_code', 7002)
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    // Get Settle Amount - cumulative
                    $settleAmount = ImpressSettlement::where('head', $currentTrno)
                        ->where('year', $year)
                        ->where('month', $currentMonth)
                        ->sum('amount');

                    // Add to cumulative totals
                    $cumulativeDR += $drAmount;
                    $cumulativeIssue += $issueAmount;
                    $cumulativeCR += $crAmount;
                    $cumulativeSettle += $settleAmount;
                }

                // Calculate Grand Total (cumulative)
                $grandTotal = $openingBalanceAmount + $cumulativeDR + $cumulativeIssue - $cumulativeCR - $cumulativeSettle;

                $results[] = [
                    'trno' => $currentTrno,
                    'opening_balance' => round($openingBalanceAmount, 2),
                    'dr_amount' => round($cumulativeDR, 2),
                    'issue_amount' => round($cumulativeIssue, 2),
                    'cr_amount' => round($cumulativeCR, 2),
                    'settle_amount' => round($cumulativeSettle, 2),
                    'grand_total' => round($grandTotal, 2),
                ];

                $grandTotalOpeningBalance += $openingBalanceAmount;
                $grandTotalDR += $cumulativeDR;
                $grandTotalIssue += $cumulativeIssue;
                $grandTotalCR += $cumulativeCR;
                $grandTotalSettle += $cumulativeSettle;
                $grandTotalBalance += $grandTotal;
            }

            // Get month names for display
            $monthNames = $this->getMonthNames();
            $monthNamesToShow = [];
            foreach ($monthsToShow as $monthNum) {
                $monthNamesToShow[$monthNum] = $monthNames[$monthNum];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'months' => $monthsToShow,
                    'month_names' => $monthNamesToShow,
                    'grand_totals' => [
                        'total_opening_balance' => round($grandTotalOpeningBalance, 2),
                        'total_dr' => round($grandTotalDR, 2),
                        'total_issue' => round($grandTotalIssue, 2),
                        'total_cr' => round($grandTotalCR, 2),
                        'total_settle' => round($grandTotalSettle, 2),
                        'total_grand' => round($grandTotalBalance, 2),
                    ],
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                        'trno' => $trno,
                        'view_type' => $viewType,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in ImprestBalance getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get month names
     */
    private function getMonthNames()
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];
    }

    /**
     * Get filter options (years, months, and TRNOs)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');

            // Get available years
            $years = MonthlyFincance::select(DB::raw('YEAR(created_at) as year'))
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear));
            }

            // Months 1-12
            $months = collect(range(1, 12));

            // Get TRNOs based on year and month
            $trnosQuery = MonthlyFincance::whereNotNull('trno');
            
            if ($year) {
                $trnosQuery->whereYear('created_at', $year);
            }
            if ($month) {
                $trnosQuery->where('month', $month);
            }
            
            $trnos = $trnosQuery->distinct()
                ->orderBy('trno')
                ->pluck('trno')
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                    'months' => $months,
                    'trnos' => $trnos,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in ImprestBalance getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV
     */
    public function export(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $trno = $request->input('trno');
            $viewType = $request->input('view_type', 'cumulative');

            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
                ], 422);
            }

            if ($viewType === 'cumulative') {
                $monthsToShow = range(1, (int)$month);
            } else {
                $monthsToShow = [(int)$month];
            }

            $trnosQuery = MonthlyFincance::whereYear('created_at', $year)
                ->whereIn('month', $monthsToShow);
            
            if ($trno) {
                $trnosQuery->where('trno', $trno);
            }

            $trnos = $trnosQuery->distinct()
                ->orderBy('trno')
                ->pluck('trno')
                ->values();

            if ($trnos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data to export'
                ], 404);
            }

            $exportData = [];
            $grandTotalOpeningBalance = 0;
            $grandTotalDR = 0;
            $grandTotalIssue = 0;
            $grandTotalCR = 0;
            $grandTotalSettle = 0;
            $grandTotalBalance = 0;

            foreach ($trnos as $currentTrno) {
                $openingBalance = OpeningBalance::where('head', $currentTrno)
                    ->where('year', $year)
                    ->first();

                $openingBalanceAmount = $openingBalance ? floatval($openingBalance->opening_balance) : 0;

                $cumulativeDR = 0;
                $cumulativeIssue = 0;
                $cumulativeCR = 0;
                $cumulativeSettle = 0;

                foreach ($monthsToShow as $currentMonth) {
                    $drAmount = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', $currentTrno)
                        ->where('dr_cr_code', 7002)
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    $issueAmount = ImpressIssue::where('head', $currentTrno)
                        ->where('year', $year)
                        ->where('month', $currentMonth)
                        ->sum('amount');

                    $crAmount = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', $currentTrno)
                        ->where('dr_cr_code', 7002)
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $settleAmount = ImpressSettlement::where('head', $currentTrno)
                        ->where('year', $year)
                        ->where('month', $currentMonth)
                        ->sum('amount');

                    $cumulativeDR += $drAmount;
                    $cumulativeIssue += $issueAmount;
                    $cumulativeCR += $crAmount;
                    $cumulativeSettle += $settleAmount;
                }

                $grandTotal = $openingBalanceAmount + $cumulativeDR + $cumulativeIssue - $cumulativeCR - $cumulativeSettle;

                $exportData[] = [
                    'TR No' => $currentTrno,
                    'Opening Balance' => round($openingBalanceAmount, 2),
                    'DR Amount' => round($cumulativeDR, 2),
                    'Issue Amount' => round($cumulativeIssue, 2),
                    'CR Amount' => round($cumulativeCR, 2),
                    'Settle Amount' => round($cumulativeSettle, 2),
                    'Grand Total' => round($grandTotal, 2),
                ];

                $grandTotalOpeningBalance += $openingBalanceAmount;
                $grandTotalDR += $cumulativeDR;
                $grandTotalIssue += $cumulativeIssue;
                $grandTotalCR += $cumulativeCR;
                $grandTotalSettle += $cumulativeSettle;
                $grandTotalBalance += $grandTotal;
            }

            // Add grand total row
            $exportData[] = [
                'TR No' => 'GRAND TOTAL',
                'Opening Balance' => round($grandTotalOpeningBalance, 2),
                'DR Amount' => round($grandTotalDR, 2),
                'Issue Amount' => round($grandTotalIssue, 2),
                'CR Amount' => round($grandTotalCR, 2),
                'Settle Amount' => round($grandTotalSettle, 2),
                'Grand Total' => round($grandTotalBalance, 2),
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'total_records' => count($exportData)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}