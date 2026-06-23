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
            $totalDepositDR = 0;
            $totalCommercialDR = 0;
            $totalAdvanceDR = 0;
            $totalProvFundDR = 0;
            $totalSurchargeCR = 0;
            $totalRevenueCR = 0;
            $totalDepositCR = 0;
            $totalCommercialCR = 0;
            $totalAdvanceCR = 0;
            $totalProvFundCR = 0;
            $totalDebit = 0;
            $totalCredit = 0;
            $totalExpenditureDRCR = 0;

            foreach ($trnos as $trno) {
                // DR Transactions
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

                $depositDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 6000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $commercialDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 7000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $advanceDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8493)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $provFundDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8098)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // CR Transactions
                $surchargeCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 2000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $revenueCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 4000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $depositCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 6000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $commercialCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 7000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $advanceCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8493)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $provFundCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8098)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Calculate Total Debit (Sum of all DR)
                $totalDebitAmount = $expenditure + $refundRevenue + $depositDR + $commercialDR + $advanceDR + $provFundDR;
                
                // Calculate Total Credit (Sum of all CR)
                $totalCreditAmount = $surchargeCR + $revenueCR + $depositCR + $commercialCR + $advanceCR + $provFundCR;
                
                // Calculate Expenditure (DR - CR)
                $expenditureDRCR = $totalDebitAmount - $totalCreditAmount;

                $results[] = [
                    'trno' => $trno,
                    'expenditure' => round($expenditure, 2),
                    'refund_revenue' => round($refundRevenue, 2),
                    'deposit_dr' => round($depositDR, 2),
                    'commercial_dr' => round($commercialDR, 2),
                    'advance_dr' => round($advanceDR, 2),
                    'prov_fund_dr' => round($provFundDR, 2),
                    'surcharge_cr' => round($surchargeCR, 2),
                    'revenue_cr' => round($revenueCR, 2),
                    'deposit_cr' => round($depositCR, 2),
                    'commercial_cr' => round($commercialCR, 2),
                    'advance_cr' => round($advanceCR, 2),
                    'prov_fund_cr' => round($provFundCR, 2),
                    'total_debit' => round($totalDebitAmount, 2),
                    'total_credit' => round($totalCreditAmount, 2),
                    'expenditure_dr_cr' => round($expenditureDRCR, 2),
                ];

                // DR Totals
                $totalExpenditure += $expenditure;
                $totalRefundRevenue += $refundRevenue;
                $totalDepositDR += $depositDR;
                $totalCommercialDR += $commercialDR;
                $totalAdvanceDR += $advanceDR;
                $totalProvFundDR += $provFundDR;
                
                // CR Totals
                $totalSurchargeCR += $surchargeCR;
                $totalRevenueCR += $revenueCR;
                $totalDepositCR += $depositCR;
                $totalCommercialCR += $commercialCR;
                $totalAdvanceCR += $advanceCR;
                $totalProvFundCR += $provFundCR;
                
                $totalDebit += $totalDebitAmount;
                $totalCredit += $totalCreditAmount;
                $totalExpenditureDRCR += $expenditureDRCR;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => [
                        'total_expenditure' => round($totalExpenditure, 2),
                        'total_refund_revenue' => round($totalRefundRevenue, 2),
                        'total_deposit_dr' => round($totalDepositDR, 2),
                        'total_commercial_dr' => round($totalCommercialDR, 2),
                        'total_advance_dr' => round($totalAdvanceDR, 2),
                        'total_prov_fund_dr' => round($totalProvFundDR, 2),
                        'total_surcharge_cr' => round($totalSurchargeCR, 2),
                        'total_revenue_cr' => round($totalRevenueCR, 2),
                        'total_deposit_cr' => round($totalDepositCR, 2),
                        'total_commercial_cr' => round($totalCommercialCR, 2),
                        'total_advance_cr' => round($totalAdvanceCR, 2),
                        'total_prov_fund_cr' => round($totalProvFundCR, 2),
                        'total_debit' => round($totalDebit, 2),
                        'total_credit' => round($totalCredit, 2),
                        'total_expenditure_dr_cr' => round($totalExpenditureDRCR, 2),
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
            $years = MonthlyFincance::select(DB::raw('YEAR(created_at) as year'))
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear));
            }

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
            $totalDepositDR = 0;
            $totalCommercialDR = 0;
            $totalAdvanceDR = 0;
            $totalProvFundDR = 0;
            $totalSurchargeCR = 0;
            $totalRevenueCR = 0;
            $totalDepositCR = 0;
            $totalCommercialCR = 0;
            $totalAdvanceCR = 0;
            $totalProvFundCR = 0;
            $totalDebit = 0;
            $totalCredit = 0;
            $totalExpenditureDRCR = 0;

            foreach ($trnos as $trno) {
                // DR
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

                $depositDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 6000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $commercialDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 7000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $advanceDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8493)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $provFundDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8098)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // CR
                $surchargeCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 2000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $revenueCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 4000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $depositCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 6000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $commercialCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 7000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $advanceCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8493)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $provFundCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 8098)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $totalDebitAmount = $expenditure + $refundRevenue + $depositDR + $commercialDR + $advanceDR + $provFundDR;
                $totalCreditAmount = $surchargeCR + $revenueCR + $depositCR + $commercialCR + $advanceCR + $provFundCR;
                $expenditureDRCR = $totalDebitAmount - $totalCreditAmount;

                $exportData[] = [
                    'TR No' => $trno,
                    'Expenditure (DR)' => round($expenditure, 2),
                    'Refund Revenue (DR)' => round($refundRevenue, 2),
                    'Deposit (DR)' => round($depositDR, 2),
                    'Commercial (DR)' => round($commercialDR, 2),
                    'Advance (DR)' => round($advanceDR, 2),
                    'Prov Fund (DR)' => round($provFundDR, 2),
                    'Surcharge (CR)' => round($surchargeCR, 2),
                    'Revenue (CR)' => round($revenueCR, 2),
                    'Deposit (CR)' => round($depositCR, 2),
                    'Commercial (CR)' => round($commercialCR, 2),
                    'Advance (CR)' => round($advanceCR, 2),
                    'Prov Fund (CR)' => round($provFundCR, 2),
                    'Total Debit' => round($totalDebitAmount, 2),
                    'Total Credit' => round($totalCreditAmount, 2),
                    'Expenditure (DR-CR)' => round($expenditureDRCR, 2),
                ];

                $totalExpenditure += $expenditure;
                $totalRefundRevenue += $refundRevenue;
                $totalDepositDR += $depositDR;
                $totalCommercialDR += $commercialDR;
                $totalAdvanceDR += $advanceDR;
                $totalProvFundDR += $provFundDR;
                $totalSurchargeCR += $surchargeCR;
                $totalRevenueCR += $revenueCR;
                $totalDepositCR += $depositCR;
                $totalCommercialCR += $commercialCR;
                $totalAdvanceCR += $advanceCR;
                $totalProvFundCR += $provFundCR;
                $totalDebit += $totalDebitAmount;
                $totalCredit += $totalCreditAmount;
                $totalExpenditureDRCR += $expenditureDRCR;
            }

            $exportData[] = [
                'TR No' => 'TOTAL',
                'Expenditure (DR)' => round($totalExpenditure, 2),
                'Refund Revenue (DR)' => round($totalRefundRevenue, 2),
                'Deposit (DR)' => round($totalDepositDR, 2),
                'Commercial (DR)' => round($totalCommercialDR, 2),
                'Advance (DR)' => round($totalAdvanceDR, 2),
                'Prov Fund (DR)' => round($totalProvFundDR, 2),
                'Surcharge (CR)' => round($totalSurchargeCR, 2),
                'Revenue (CR)' => round($totalRevenueCR, 2),
                'Deposit (CR)' => round($totalDepositCR, 2),
                'Commercial (CR)' => round($totalCommercialCR, 2),
                'Advance (CR)' => round($totalAdvanceCR, 2),
                'Prov Fund (CR)' => round($totalProvFundCR, 2),
                'Total Debit' => round($totalDebit, 2),
                'Total Credit' => round($totalCredit, 2),
                'Expenditure (DR-CR)' => round($totalExpenditureDRCR, 2),
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