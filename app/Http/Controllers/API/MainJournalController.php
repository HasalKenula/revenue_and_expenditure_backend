<?php

// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use App\Models\MonthlyFincance;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;

// class MainJournalController extends Controller
// {
//     /**
//      * Get Main Journal data
//      */
//     public function getData(Request $request)
//     {
//         try {
//             $year = $request->input('year');
//             $month = $request->input('month');
//             $trno = $request->input('trno');
            
//             \Log::info('MainJournal getData called', [
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

//             // If TR No is provided, filter by it
//             $query = MonthlyFincance::whereYear('created_at', $year)
//                 ->where('month', $month);
            
//             if ($trno) {
//                 $query->where('trno', $trno);
//             }

//             // Get all distinct TRNOs
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

//             // Define account types with their codes (using trno as head)
//             $accountTypes = [
//                 'deposit' => [
//                     'label' => 'Deposit a/c',
//                     'code' => 6000
//                 ],
//                 'expenditure' => [
//                     'label' => 'Expenditure a/c',
//                     'code' => 1000
//                 ],
//                 'public_officer_advance' => [
//                     'label' => 'Public Officers Advance a/c',
//                     'code' => 8493
//                 ],
//                 'public_service_prov_fund' => [
//                     'label' => 'Public Service Prov. Fund',
//                     'code' => 8098
//                 ],
//                 'revenue' => [
//                     'label' => 'Revenue a/c',
//                     'code' => 4000
//                 ],
//                 'surcharge' => [
//                     'label' => 'Surcharge a/c',
//                     'code' => 2000
//                 ]
//             ];

//             $results = [];
//             $grandTotalDebit = 0;
//             $grandTotalCredit = 0;

//             foreach ($trnos as $currentTrno) {
//                 $row = [
//                     'trno' => $currentTrno,
//                     'accounts' => []
//                 ];

//                 $totalDebits = 0;
//                 $totalCredits = 0;

//                 foreach ($accountTypes as $key => $account) {
//                     // Get DR amount
//                     $drAmount = MonthlyFincance::whereYear('created_at', $year)
//                         ->where('month', $month)
//                         ->where('trno', $currentTrno)
//                         ->where('dr_cr_code', $account['code'])
//                         ->where('dr_cr', 'DR')
//                         ->sum('cash_xe');

//                     // Get CR amount
//                     $crAmount = MonthlyFincance::whereYear('created_at', $year)
//                         ->where('month', $month)
//                         ->where('trno', $currentTrno)
//                         ->where('dr_cr_code', $account['code'])
//                         ->where('dr_cr', 'CR')
//                         ->sum('cash_xe');

//                     $row['accounts'][$key] = [
//                         'label' => $account['label'],
//                         'debit' => round($drAmount, 2),
//                         'credit' => round($crAmount, 2)
//                     ];

//                     $totalDebits += $drAmount;
//                     $totalCredits += $crAmount;
//                 }

//                 $row['total_debits'] = round($totalDebits, 2);
//                 $row['total_credits'] = round($totalCredits, 2);

//                 $results[] = $row;

//                 $grandTotalDebit += $totalDebits;
//                 $grandTotalCredit += $totalCredits;
//             }

//             // Calculate grand balance
//             $grandBalance = $grandTotalDebit - $grandTotalCredit;

//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'records' => $results,
//                     'account_types' => $accountTypes,
//                     'grand_totals' => [
//                         'total_debits' => round($grandTotalDebit, 2),
//                         'total_credits' => round($grandTotalCredit, 2),
//                         'balance' => round(abs($grandBalance), 2),
//                         'balance_side' => $grandBalance >= 0 ? 'Credit' : 'Debit'
//                     ],
//                     'filters' => [
//                         'year' => $year,
//                         'month' => $month,
//                         'trno' => $trno,
//                     ]
//                 ]
//             ]);

//         } catch (\Exception $e) {
//             \Log::error('Error in MainJournal getData: ' . $e->getMessage());
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
//             \Log::error('Error in MainJournal getFilterOptions: ' . $e->getMessage());
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

//             $accountTypes = [
//                 'deposit' => 'Deposit a/c',
//                 'expenditure' => 'Expenditure a/c',
//                 'public_officer_advance' => 'Public Officers Advance a/c',
//                 'public_service_prov_fund' => 'Public Service Prov. Fund',
//                 'revenue' => 'Revenue a/c',
//                 'surcharge' => 'Surcharge a/c'
//             ];

//             $accountCodes = [
//                 'deposit' => 6000,
//                 'expenditure' => 1000,
//                 'public_officer_advance' => 8493,
//                 'public_service_prov_fund' => 8098,
//                 'revenue' => 4000,
//                 'surcharge' => 2000
//             ];

//             $exportData = [];
//             $grandTotalDebit = 0;
//             $grandTotalCredit = 0;

//             foreach ($trnos as $currentTrno) {
//                 $row = ['TR No' => $currentTrno];
//                 $totalDebits = 0;
//                 $totalCredits = 0;

//                 foreach ($accountTypes as $key => $label) {
//                     $drAmount = MonthlyFincance::whereYear('created_at', $year)
//                         ->where('month', $month)
//                         ->where('trno', $currentTrno)
//                         ->where('dr_cr_code', $accountCodes[$key])
//                         ->where('dr_cr', 'DR')
//                         ->sum('cash_xe');

//                     $crAmount = MonthlyFincance::whereYear('created_at', $year)
//                         ->where('month', $month)
//                         ->where('trno', $currentTrno)
//                         ->where('dr_cr_code', $accountCodes[$key])
//                         ->where('dr_cr', 'CR')
//                         ->sum('cash_xe');

//                     $row[$label . ' (DR)'] = round($drAmount, 2);
//                     $row[$label . ' (CR)'] = round($crAmount, 2);
                    
//                     $totalDebits += $drAmount;
//                     $totalCredits += $crAmount;
//                 }

//                 $row['Total Debits'] = round($totalDebits, 2);
//                 $row['Total Credits'] = round($totalCredits, 2);

//                 $exportData[] = $row;
//                 $grandTotalDebit += $totalDebits;
//                 $grandTotalCredit += $totalCredits;
//             }

//             // Add grand total row
//             $grandTotalRow = ['TR No' => 'GRAND TOTAL'];
//             foreach ($accountTypes as $key => $label) {
//                 $grandTotalRow[$label . ' (DR)'] = '';
//                 $grandTotalRow[$label . ' (CR)'] = '';
//             }
//             $grandTotalRow['Total Debits'] = round($grandTotalDebit, 2);
//             $grandTotalRow['Total Credits'] = round($grandTotalCredit, 2);
            
//             $exportData[] = $grandTotalRow;

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainJournalController extends Controller
{
    /**
     * Get Main Journal data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $trno = $request->input('trno');
            
            \Log::info('MainJournal getData called', [
                'year' => $year,
                'month' => $month,
                'trno' => $trno
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

            // If TR No is provided, filter by it
            $query = MonthlyFincance::whereYear('created_at', $year)
                ->where('month', $month);
            
            if ($trno) {
                $query->where('trno', $trno);
            }

            // Get all distinct TRNOs
            $trnos = $query->distinct()
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

            // Define account types with their codes
            $accountTypes = [
                'deposit' => [
                    'label' => 'Deposit a/c',
                    'code' => 6000
                ],
                'expenditure' => [
                    'label' => 'Expenditure a/c',
                    'code' => 1000
                ],
                'public_officer_advance' => [
                    'label' => 'Public Officers Advance a/c',
                    'code' => 8493
                ],
                'public_service_prov_fund' => [
                    'label' => 'Public Service Prov. Fund',
                    'code' => 8098
                ],
                'revenue' => [
                    'label' => 'Revenue a/c',
                    'code' => 4000
                ],
                'surcharge' => [
                    'label' => 'Surcharge a/c',
                    'code' => 2000
                ]
            ];

            $results = [];
            $grandTotalDebit = 0;
            $grandTotalCredit = 0;

            foreach ($trnos as $currentTrno) {
                $row = [
                    'trno' => $currentTrno,
                    'accounts' => []
                ];

                $totalDebits = 0;
                $totalCredits = 0;

                foreach ($accountTypes as $key => $account) {
                    // Get DR amount
                    $drAmount = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $month)
                        ->where('trno', $currentTrno)
                        ->where('dr_cr_code', $account['code'])
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    // Get CR amount
                    $crAmount = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $month)
                        ->where('trno', $currentTrno)
                        ->where('dr_cr_code', $account['code'])
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $row['accounts'][$key] = [
                        'label' => $account['label'],
                        'debit' => round($drAmount, 2),
                        'credit' => round($crAmount, 2)
                    ];

                    $totalDebits += $drAmount;
                    $totalCredits += $crAmount;
                }

                $row['total_debits'] = round($totalDebits, 2);
                $row['total_credits'] = round($totalCredits, 2);

                // Calculate balance
                $balanceDebit = 0;
                $balanceCredit = 0;
                $diff = $totalDebits - $totalCredits;
                
                if ($diff > 0) {
                    $balanceCredit = $diff; // Positive balance goes to Credit side
                } elseif ($diff < 0) {
                    $balanceDebit = abs($diff); // Negative balance goes to Debit side
                }

                $row['balance_debit'] = round($balanceDebit, 2);
                $row['balance_credit'] = round($balanceCredit, 2);

                $results[] = $row;

                $grandTotalDebit += $totalDebits;
                $grandTotalCredit += $totalCredits;
            }

            // Calculate grand balance
            $grandDiff = $grandTotalDebit - $grandTotalCredit;
            $grandBalanceDebit = 0;
            $grandBalanceCredit = 0;
            
            if ($grandDiff > 0) {
                $grandBalanceCredit = $grandDiff;
            } elseif ($grandDiff < 0) {
                $grandBalanceDebit = abs($grandDiff);
            }

            // Calculate grand totals with balance
            $grandTotalDebitWithBalance = $grandTotalDebit + $grandBalanceDebit;
            $grandTotalCreditWithBalance = $grandTotalCredit + $grandBalanceCredit;

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'account_types' => $accountTypes,
                    'grand_totals' => [
                        'total_debits' => round($grandTotalDebit, 2),
                        'total_credits' => round($grandTotalCredit, 2),
                        'balance_debit' => round($grandBalanceDebit, 2),
                        'balance_credit' => round($grandBalanceCredit, 2),
                        'total_debits_with_balance' => round($grandTotalDebitWithBalance, 2),
                        'total_credits_with_balance' => round($grandTotalCreditWithBalance, 2),
                    ],
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                        'trno' => $trno,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in MainJournal getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
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
            \Log::error('Error in MainJournal getFilterOptions: ' . $e->getMessage());
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

            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
                ], 422);
            }

            // Get data using the same logic
            $query = MonthlyFincance::whereYear('created_at', $year)
                ->where('month', $month);
            
            if ($trno) {
                $query->where('trno', $trno);
            }

            $trnos = $query->distinct()
                ->orderBy('trno')
                ->pluck('trno')
                ->values();

            if ($trnos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data to export'
                ], 404);
            }

            $accountCodes = [
                'deposit' => 6000,
                'expenditure' => 1000,
                'public_officer_advance' => 8493,
                'public_service_prov_fund' => 8098,
                'revenue' => 4000,
                'surcharge' => 2000
            ];

            $accountLabels = [
                'deposit' => 'Deposit a/c',
                'expenditure' => 'Expenditure a/c',
                'public_officer_advance' => 'Public Officers Advance a/c',
                'public_service_prov_fund' => 'Public Service Prov. Fund',
                'revenue' => 'Revenue a/c',
                'surcharge' => 'Surcharge a/c'
            ];

            $exportData = [];
            $grandTotalDebit = 0;
            $grandTotalCredit = 0;

            foreach ($trnos as $currentTrno) {
                $row = ['TR No' => $currentTrno];
                $totalDebits = 0;
                $totalCredits = 0;

                foreach ($accountCodes as $key => $code) {
                    $drAmount = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $month)
                        ->where('trno', $currentTrno)
                        ->where('dr_cr_code', $code)
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    $crAmount = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $month)
                        ->where('trno', $currentTrno)
                        ->where('dr_cr_code', $code)
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $row[$accountLabels[$key] . ' (DR)'] = round($drAmount, 2);
                    $row[$accountLabels[$key] . ' (CR)'] = round($crAmount, 2);
                    
                    $totalDebits += $drAmount;
                    $totalCredits += $crAmount;
                }

                $row['Total Debits'] = round($totalDebits, 2);
                $row['Total Credits'] = round($totalCredits, 2);

                // Balance
                $diff = $totalDebits - $totalCredits;
                $row['Balance Debit'] = $diff < 0 ? round(abs($diff), 2) : 0;
                $row['Balance Credit'] = $diff > 0 ? round($diff, 2) : 0;

                $exportData[] = $row;
                $grandTotalDebit += $totalDebits;
                $grandTotalCredit += $totalCredits;
            }

            // Add grand total row
            $grandDiff = $grandTotalDebit - $grandTotalCredit;
            $grandBalanceDebit = $grandDiff < 0 ? round(abs($grandDiff), 2) : 0;
            $grandBalanceCredit = $grandDiff > 0 ? round($grandDiff, 2) : 0;

            $grandTotalRow = ['TR No' => 'GRAND TOTAL'];
            foreach ($accountLabels as $key => $label) {
                $grandTotalRow[$label . ' (DR)'] = '';
                $grandTotalRow[$label . ' (CR)'] = '';
            }
            $grandTotalRow['Total Debits'] = round($grandTotalDebit, 2);
            $grandTotalRow['Total Credits'] = round($grandTotalCredit, 2);
            $grandTotalRow['Balance Debit'] = $grandBalanceDebit;
            $grandTotalRow['Balance Credit'] = $grandBalanceCredit;
            
            $exportData[] = $grandTotalRow;

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