<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalSummaryController extends Controller
{
    /**
     * Get Journal Summary data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            
            \Log::info('JournalSummary getData called', [
                'year' => $year,
                'month' => $month
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

            // Get all distinct TRNOs for the selected year and month
            $trnos = MonthlyFincance::whereYear('created_at', $year)
                ->where('month', $month)
                ->distinct()
                ->orderBy('trno')
                ->pluck('trno')
                ->values();

            if ($trnos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No records found for the selected year and month'
                ]);
            }

            $results = [];
            $totalExpenditure = 0;
            $totalRefundRevenue = 0;
            $totalDeposit = 0;
            $totalCommercial = 0;
            $totalAdvance = 0;
            $totalProvFund = 0;
            $totalDebit = 0;

            foreach ($trnos as $trno) {
                // Get Expenditure (A/C) - DR (1000) with DR = 'DR'
                $expenditure = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 1000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get Refund Revenue - DR (5000) with DR = 'DR'
                $refundRevenue = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 5000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get Deposit (AC) - DR (6000) with DR = 'DR'
                $deposit = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 6000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get Commercial (AC) - DR (7000) with DR = 'DR'
                $commercial = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 7000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get Advance (AC) - DR (8493) with DR = 'DR'
                $advance = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8493)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get Prov Fund (AC) - DR (8098) with DR = 'DR'
                $provFund = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8098)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Calculate Total Debit
                $totalDebitAmount = $expenditure + $refundRevenue + $deposit + $commercial + $advance + $provFund;

                $results[] = [
                    'trno' => $trno,
                    'expenditure' => round($expenditure, 2),
                    'refund_revenue' => round($refundRevenue, 2),
                    'deposit' => round($deposit, 2),
                    'commercial' => round($commercial, 2),
                    'advance' => round($advance, 2),
                    'prov_fund' => round($provFund, 2),
                    'total_debit' => round($totalDebitAmount, 2),
                ];

                $totalExpenditure += $expenditure;
                $totalRefundRevenue += $refundRevenue;
                $totalDeposit += $deposit;
                $totalCommercial += $commercial;
                $totalAdvance += $advance;
                $totalProvFund += $provFund;
                $totalDebit += $totalDebitAmount;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => [
                        'total_expenditure' => round($totalExpenditure, 2),
                        'total_refund_revenue' => round($totalRefundRevenue, 2),
                        'total_deposit' => round($totalDeposit, 2),
                        'total_commercial' => round($totalCommercial, 2),
                        'total_advance' => round($totalAdvance, 2),
                        'total_prov_fund' => round($totalProvFund, 2),
                        'total_debit' => round($totalDebit, 2),
                        'total_records' => count($results)
                    ],
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in JournalSummary getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get filter options (years and months)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from created_at
            $years = MonthlyFincance::select(DB::raw('YEAR(created_at) as year'))
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            // If no years found, provide default range
            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear));
            }

            // Months 1-12
            $months = collect(range(1, 12));

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                    'months' => $months,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in JournalSummary getFilterOptions: ' . $e->getMessage());
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

            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
                ], 422);
            }

            // Get data using the same logic
            $trnos = MonthlyFincance::whereYear('created_at', $year)
                ->where('month', $month)
                ->distinct()
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
            $totalExpenditure = 0;
            $totalRefundRevenue = 0;
            $totalDeposit = 0;
            $totalCommercial = 0;
            $totalAdvance = 0;
            $totalProvFund = 0;
            $totalDebit = 0;

            foreach ($trnos as $trno) {
                $expenditure = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 1000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $refundRevenue = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 5000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $deposit = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 6000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $commercial = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 7000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $advance = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8493)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $provFund = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8098)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $totalDebitAmount = $expenditure + $refundRevenue + $deposit + $commercial + $advance + $provFund;

                $exportData[] = [
                    'TR No' => $trno,
                    'Expenditure (A/C)' => round($expenditure, 2),
                    'Refund Revenue' => round($refundRevenue, 2),
                    'Deposit (AC)' => round($deposit, 2),
                    'Commercial (AC)' => round($commercial, 2),
                    'Advance (AC)' => round($advance, 2),
                    'Prov Fund (AC)' => round($provFund, 2),
                    'Total Debit' => round($totalDebitAmount, 2),
                ];

                $totalExpenditure += $expenditure;
                $totalRefundRevenue += $refundRevenue;
                $totalDeposit += $deposit;
                $totalCommercial += $commercial;
                $totalAdvance += $advance;
                $totalProvFund += $provFund;
                $totalDebit += $totalDebitAmount;
            }

            // Add totals row
            $exportData[] = [
                'TR No' => 'TOTAL',
                'Expenditure (A/C)' => round($totalExpenditure, 2),
                'Refund Revenue' => round($totalRefundRevenue, 2),
                'Deposit (AC)' => round($totalDeposit, 2),
                'Commercial (AC)' => round($totalCommercial, 2),
                'Advance (AC)' => round($totalAdvance, 2),
                'Prov Fund (AC)' => round($totalProvFund, 2),
                'Total Debit' => round($totalDebit, 2),
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