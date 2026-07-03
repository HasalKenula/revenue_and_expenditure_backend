<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\MonthlyFincance;
use App\Models\SupplementaryRecord;
use App\Models\OpeningBalance;
use App\Models\ImpressIssue;
use App\Models\ImpressSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function getDashboardData(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            $month = $request->input('month', date('n'));
            
            \Log::info('Dashboard getData called', [
                'year' => $year,
                'month' => $month
            ]);

            // ========== 1. BUDGET STATISTICS ==========
            $totalBudget = Budget::sum('amount') ?? 0;
            
            $totalBudgetByHead = Budget::select('head', DB::raw('SUM(amount) as total'))
                ->whereNotNull('head')
                ->groupBy('head')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();
            
            $totalBudgetByProgram = Budget::select('program', DB::raw('SUM(amount) as total'))
                ->whereNotNull('program')
                ->groupBy('program')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            // ========== 2. MONTHLY FINANCE STATISTICS ==========
            // Use year column instead of created_at for filtering
            $totalDebits = MonthlyFincance::where('year', $year)
                ->where('dr_cr', 'DR')
                ->sum('cash_xe') ?? 0;
            
            $totalCredits = MonthlyFincance::where('year', $year)
                ->where('dr_cr', 'CR')
                ->sum('cash_xe') ?? 0;
            
            // Monthly trend
            $monthlyTrend = MonthlyFincance::select(
                    'month',
                    DB::raw('SUM(CASE WHEN dr_cr = "DR" THEN cash_xe ELSE 0 END) as total_debit'),
                    DB::raw('SUM(CASE WHEN dr_cr = "CR" THEN cash_xe ELSE 0 END) as total_credit')
                )
                ->where('year', $year)
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Current month statistics
            $currentMonthDebits = MonthlyFincance::where('year', $year)
                ->where('month', $month)
                ->where('dr_cr', 'DR')
                ->sum('cash_xe') ?? 0;
            
            $currentMonthCredits = MonthlyFincance::where('year', $year)
                ->where('month', $month)
                ->where('dr_cr', 'CR')
                ->sum('cash_xe') ?? 0;

            // Top 10 TRNOs by expenditure
            $topTrnos = MonthlyFincance::where('year', $year)
                ->where('dr_cr', 'DR')
                ->select('trno', DB::raw('SUM(cash_xe) as total'))
                ->whereNotNull('trno')
                ->groupBy('trno')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            // ========== 3. SUPPLEMENTARY RECORDS STATISTICS ==========
            $totalSupplementary = SupplementaryRecord::where('year', $year)
                ->sum('supplementary_amount') ?? 0;
            
            $totalFr66p = SupplementaryRecord::where('year', $year)
                ->sum('fr66p') ?? 0;
            
            $totalFr66m = SupplementaryRecord::where('year', $year)
                ->sum('fr66m') ?? 0;

            // Supplementary by month
            $supplementaryByMonth = SupplementaryRecord::where('year', $year)
                ->select('month', DB::raw('SUM(supplementary_amount) as total'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // ========== 4. OPENING BALANCE STATISTICS ==========
            $totalOpeningBalance = OpeningBalance::where('year', $year)->sum('opening_balance') ?? 0;
            
            $openingBalancesByHead = OpeningBalance::where('year', $year)
                ->select('head', 'opening_balance')
                ->orderBy('opening_balance', 'desc')
                ->limit(10)
                ->get();

            // ========== 5. IMPRESS STATISTICS ==========
            $totalImpressIssued = ImpressIssue::where('year', $year)
                ->sum('amount') ?? 0;
            
            $totalImpressSettled = ImpressSettlement::where('year', $year)
                ->sum('amount') ?? 0;
            
            $netImpressBalance = $totalImpressIssued - $totalImpressSettled;

            $impressByMonth = ImpressIssue::where('year', $year)
                ->select('month', DB::raw('SUM(amount) as total_issued'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $settlementByMonth = ImpressSettlement::where('year', $year)
                ->select('month', DB::raw('SUM(amount) as total_settled'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // ========== 6. BUDGET VS ACTUAL ==========
            $budgetVsActual = Budget::select(
                    'budgets.object',
                    'budgets.objname',
                    DB::raw('SUM(budgets.amount) as budgeted'),
                    DB::raw('COALESCE(SUM(mf.cash_xe), 0) as actual')
                )
                ->leftJoin('monthly_fincances as mf', function($join) use ($year, $month) {
                    $join->on('budgets.object', '=', 'mf.object')
                         ->where('mf.dr_cr', 'DR')
                         ->where('mf.year', $year)
                         ->where('mf.month', '<=', $month);
                })
                ->whereNotNull('budgets.object')
                ->groupBy('budgets.object', 'budgets.objname')
                ->havingRaw('SUM(budgets.amount) > 0')
                ->orderBy('budgets.object')
                ->limit(20)
                ->get();

            $budgetVsActual = $budgetVsActual->map(function($item) {
                $item->variance = ($item->budgeted ?? 0) - ($item->actual ?? 0);
                $item->utilization = ($item->budgeted ?? 0) > 0 
                    ? round((($item->actual ?? 0) / ($item->budgeted ?? 0)) * 100, 2) 
                    : 0;
                return $item;
            });

            // ========== 7. SUMMARY CARDS ==========
            $summary = [
                'total_budget' => round($totalBudget, 2),
                'total_debits' => round($totalDebits, 2),
                'total_credits' => round($totalCredits, 2),
                'net_balance' => round($totalDebits - $totalCredits, 2),
                'current_month_debits' => round($currentMonthDebits, 2),
                'current_month_credits' => round($currentMonthCredits, 2),
                'total_supplementary' => round($totalSupplementary, 2),
                'total_fr66p' => round($totalFr66p, 2),
                'total_fr66m' => round($totalFr66m, 2),
                'total_opening_balance' => round($totalOpeningBalance, 2),
                'total_impress_issued' => round($totalImpressIssued, 2),
                'total_impress_settled' => round($totalImpressSettled, 2),
                'net_impress_balance' => round($netImpressBalance, 2),
            ];

            // ========== 8. RECENT ACTIVITIES ==========
            $recentActivities = MonthlyFincance::where('year', $year)
                ->where('month', $month)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['trno', 'head', 'subject', 'dr_cr', 'cash_xe', 'created_at']);

            // ========== 9. ADDITIONAL METRICS ==========
            // Total records count
            $totalRecords = MonthlyFincance::where('year', $year)->count();
            $totalBudgetRecords = Budget::count();
            $totalSupplementaryRecords = SupplementaryRecord::where('year', $year)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'budget_by_head' => $totalBudgetByHead,
                    'budget_by_program' => $totalBudgetByProgram,
                    'monthly_trend' => $monthlyTrend,
                    'top_trnos' => $topTrnos,
                    'supplementary_by_month' => $supplementaryByMonth,
                    'opening_balances' => $openingBalancesByHead,
                    'impress_by_month' => $impressByMonth,
                    'settlement_by_month' => $settlementByMonth,
                    'budget_vs_actual' => $budgetVsActual,
                    'recent_activities' => $recentActivities,
                    'metrics' => [
                        'total_records' => $totalRecords,
                        'total_budget_records' => $totalBudgetRecords,
                        'total_supplementary_records' => $totalSupplementaryRecords,
                    ],
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in Dashboard getData: ' . $e->getMessage());
            \Log::error('Error trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Get filter options (years and months)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from monthly_fincances
            $years = MonthlyFincance::select('year')
                ->whereNotNull('year')
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
            \Log::error('Error in Dashboard getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}