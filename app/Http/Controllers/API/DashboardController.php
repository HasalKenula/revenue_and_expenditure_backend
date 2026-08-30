<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Estimate;
use App\Models\Treasury;
use App\Models\MonthlyFincance;
use App\Models\OpeningBalance;
use App\Models\ImpressIssue;
use App\Models\ImpressSettlement;
use App\Models\SupplementaryRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Get main dashboard data - No filters
     */
    public function index(Request $request)
    {
        try {
            Log::info('Dashboard API called');

            $data = [
                'summary_cards' => $this->getSummaryCards(),
                'revenue_chart' => $this->getRevenueChartData(),
                'expenditure_chart' => $this->getExpenditureChartData(),
                'budget_allocation' => $this->getBudgetAllocationData(),
                'monthly_trends' => $this->getMonthlyTrends(),
                'top_categories' => $this->getTopCategories(),
                'recent_activities' => $this->getRecentActivities(),
                'yearly_comparison' => $this->getYearlyComparison(),
                'impress_status' => $this->getImpressStatus(),
                'supplementary_summary' => $this->getSupplementarySummary(),
                'quick_stats' => $this->getQuickStats(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            Log::error('Dashboard error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Get summary cards data - All time totals
     */
    private function getSummaryCards()
    {
        try {
            // Total Budget
            $totalBudget = Budget::sum('amount') ?? 0;

            // Total Estimates
            $totalEstimates = Estimate::sum('estimate') ?? 0;
            $totalReEstimates = Estimate::sum('re_estimate') ?? 0;

            // Total Revenue
            $totalRevenue = Treasury::where('dr_cr', 'CR')
                ->where('dr_cr_code', 4000)
                ->sum('cash_xe') ?? 0;
            $totalRevenue += MonthlyFincance::where('dr_cr', 'CR')
                ->where('dr_cr_code', 4000)
                ->sum('cash_xe') ?? 0;

            // Total Refund
            $totalRefund = Treasury::where('dr_cr', 'DR')
                ->where('dr_cr_code', 5000)
                ->sum('cash_xe') ?? 0;
            $totalRefund += MonthlyFincance::where('dr_cr', 'DR')
                ->where('dr_cr_code', 5000)
                ->sum('cash_xe') ?? 0;

            // Net Revenue
            $netRevenue = $totalRevenue - $totalRefund;

            // Impress Balance - Latest year only
            $latestYear = ImpressIssue::max('year') ?? date('Y');
            $totalImpressIssue = ImpressIssue::where('year', $latestYear)->sum('amount') ?? 0;
            $totalImpressSettle = ImpressSettlement::where('year', $latestYear)->sum('amount') ?? 0;
            $impressBalance = $totalImpressIssue - $totalImpressSettle;

            // Total Records
            $totalRecords = [
                'budgets' => Budget::count(),
                'estimates' => Estimate::count(),
                'treasury' => Treasury::count(),
                'monthly_fincances' => MonthlyFincance::count(),
                'supplementary' => SupplementaryRecord::count(),
            ];

            return [
                'total_budget' => round($totalBudget, 2),
                'total_estimates' => round($totalEstimates, 2),
                'total_re_estimates' => round($totalReEstimates, 2),
                'total_revenue' => round($totalRevenue, 2),
                'total_refund' => round($totalRefund, 2),
                'net_revenue' => round($netRevenue, 2),
                'impress_balance' => round($impressBalance, 2),
                'total_impress_issue' => round($totalImpressIssue, 2),
                'total_impress_settle' => round($totalImpressSettle, 2),
                'total_records' => $totalRecords,
                'total_records_count' => array_sum($totalRecords),
            ];
        } catch (\Exception $e) {
            Log::error('Error in getSummaryCards: ' . $e->getMessage());
            return [
                'total_budget' => 0,
                'total_estimates' => 0,
                'total_re_estimates' => 0,
                'total_revenue' => 0,
                'total_refund' => 0,
                'net_revenue' => 0,
                'impress_balance' => 0,
                'total_impress_issue' => 0,
                'total_impress_settle' => 0,
                'total_records' => [],
                'total_records_count' => 0,
            ];
        }
    }

    /**
     * Get revenue chart data - All time monthly cumulative
     */
    private function getRevenueChartData()
    {
        try {
            $months = range(1, 12);
            $revenueData = [];
            $refundData = [];
            $netData = [];

            foreach ($months as $m) {
                // Get revenue for each month (cumulative)
                $revenue = Treasury::where('dr_cr', 'CR')
                    ->where('dr_cr_code', 4000)
                    ->where('month', '<=', $m)
                    ->sum('cash_xe') ?? 0;
                $revenue += MonthlyFincance::where('dr_cr', 'CR')
                    ->where('dr_cr_code', 4000)
                    ->where('month', '<=', $m)
                    ->sum('cash_xe') ?? 0;

                // Get refund for each month (cumulative)
                $refund = Treasury::where('dr_cr', 'DR')
                    ->where('dr_cr_code', 5000)
                    ->where('month', '<=', $m)
                    ->sum('cash_xe') ?? 0;
                $refund += MonthlyFincance::where('dr_cr', 'DR')
                    ->where('dr_cr_code', 5000)
                    ->where('month', '<=', $m)
                    ->sum('cash_xe') ?? 0;

                $revenueData[] = round($revenue, 2);
                $refundData[] = round($refund, 2);
                $netData[] = round($revenue - $refund, 2);
            }

            $monthNames = $this->getMonthNames();

            return [
                'labels' => array_values($monthNames),
                'revenue' => $revenueData,
                'refund' => $refundData,
                'net' => $netData,
            ];
        } catch (\Exception $e) {
            Log::error('Error in getRevenueChartData: ' . $e->getMessage());
            return [
                'labels' => [],
                'revenue' => [],
                'refund' => [],
                'net' => [],
            ];
        }
    }

    /**
     * Get expenditure chart data - Top 10 heads
     */
    private function getExpenditureChartData()
    {
        try {
            // Get expenditure by head (top 10)
            $expenditureByHead = Treasury::where('dr_cr', 'DR')
                ->select('head', DB::raw('SUM(cash_xe) as total'))
                ->groupBy('head')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            $labels = [];
            $values = [];
            $colors = $this->getChartColors();

            foreach ($expenditureByHead as $index => $item) {
                $labels[] = 'Head ' . ($item->head ?? 'Unknown');
                $values[] = round($item->total, 2);
            }

            return [
                'labels' => $labels,
                'values' => $values,
                'colors' => array_slice($colors, 0, count($labels)),
            ];
        } catch (\Exception $e) {
            Log::error('Error in getExpenditureChartData: ' . $e->getMessage());
            return [
                'labels' => [],
                'values' => [],
                'colors' => [],
            ];
        }
    }

    /**
     * Get budget allocation data - Top 10 heads
     */
    private function getBudgetAllocationData()
    {
        try {
            // Get budget by head
            $budgetByHead = Budget::select('head', DB::raw('SUM(amount) as total'))
                ->whereNotNull('head')
                ->groupBy('head')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            $labels = [];
            $values = [];
            $colors = $this->getChartColors();

            foreach ($budgetByHead as $index => $item) {
                $labels[] = 'Head ' . ($item->head ?? 'Unknown');
                $values[] = round($item->total, 2);
            }

            return [
                'labels' => $labels,
                'values' => $values,
                'colors' => array_slice($colors, 0, count($labels)),
            ];
        } catch (\Exception $e) {
            Log::error('Error in getBudgetAllocationData: ' . $e->getMessage());
            return [
                'labels' => [],
                'values' => [],
                'colors' => [],
            ];
        }
    }

    /**
     * Get monthly trends - All years combined
     */
    private function getMonthlyTrends()
    {
        try {
            $months = range(1, 12);
            $trends = [];

            foreach ($months as $m) {
                $revenue = Treasury::where('dr_cr', 'CR')
                    ->where('dr_cr_code', 4000)
                    ->where('month', $m)
                    ->sum('cash_xe') ?? 0;
                $revenue += MonthlyFincance::where('dr_cr', 'CR')
                    ->where('dr_cr_code', 4000)
                    ->where('month', $m)
                    ->sum('cash_xe') ?? 0;

                $expenditure = Treasury::where('dr_cr', 'DR')
                    ->where('month', $m)
                    ->sum('cash_xe') ?? 0;

                $monthName = $this->getMonthNames()[$m] ?? 'Month ' . $m;

                $trends[] = [
                    'month' => $m,
                    'month_name' => $monthName,
                    'revenue' => round($revenue, 2),
                    'expenditure' => round($expenditure, 2),
                    'net' => round($revenue - $expenditure, 2),
                ];
            }

            return $trends;
        } catch (\Exception $e) {
            Log::error('Error in getMonthlyTrends: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top revenue categories
     */
    private function getTopCategories()
    {
        try {
            // Get top revenue codes from estimates
            $topRevenue = Estimate::select('revenue_code_name', 'estimate')
                ->whereNotNull('revenue_code_name')
                ->orderBy('estimate', 'desc')
                ->limit(10)
                ->get();

            $categories = [];
            foreach ($topRevenue as $item) {
                $categories[] = [
                    'name' => $item->revenue_code_name ?? 'Unknown',
                    'estimate' => round($item->estimate ?? 0, 2),
                    'percentage' => 0,
                ];
            }

            // Calculate percentages
            $total = array_sum(array_column($categories, 'estimate'));
            foreach ($categories as &$cat) {
                $cat['percentage'] = $total > 0 ? round(($cat['estimate'] / $total) * 100, 2) : 0;
            }

            return $categories;
        } catch (\Exception $e) {
            Log::error('Error in getTopCategories: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities()
    {
        try {
            $activities = [];

            // Recent Treasury entries
            $treasuryRecent = Treasury::orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($treasuryRecent as $item) {
                $activities[] = [
                    'type' => 'treasury',
                    'title' => 'Treasury Entry',
                    'description' => "TRNO: {$item->trno}, Head: {$item->head}, Amount: " . number_format($item->cash_xe, 2),
                    'time' => $item->created_at ? $item->created_at->diffForHumans() : 'Just now',
                    'created_at' => $item->created_at,
                ];
            }

            // Recent Budget entries
            $budgetRecent = Budget::orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($budgetRecent as $item) {
                $activities[] = [
                    'type' => 'budget',
                    'title' => 'Budget Entry',
                    'description' => "Head: {$item->head}, Amount: " . number_format($item->amount, 2),
                    'time' => $item->created_at ? $item->created_at->diffForHumans() : 'Just now',
                    'created_at' => $item->created_at,
                ];
            }

            // Recent Estimate entries
            $estimateRecent = Estimate::orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($estimateRecent as $item) {
                $activities[] = [
                    'type' => 'estimate',
                    'title' => 'Estimate Entry',
                    'description' => "{$item->revenue_code_name}, Estimate: " . number_format($item->estimate, 2),
                    'time' => $item->created_at ? $item->created_at->diffForHumans() : 'Just now',
                    'created_at' => $item->created_at,
                ];
            }

            // Sort by created_at and get latest 10
            usort($activities, function ($a, $b) {
                if (!$a['created_at'] || !$b['created_at']) return 0;
                return $b['created_at'] <=> $a['created_at'];
            });

            return array_slice($activities, 0, 10);
        } catch (\Exception $e) {
            Log::error('Error in getRecentActivities: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get yearly comparison
     */
    private function getYearlyComparison()
    {
        try {
            $currentYear = date('Y');
            $years = range($currentYear - 3, $currentYear);
            $comparison = [];

            foreach ($years as $year) {
                $totalRevenue = Treasury::where('dr_cr', 'CR')
                    ->where('dr_cr_code', 4000)
                    ->where('year', $year)
                    ->sum('cash_xe') ?? 0;
                $totalRevenue += MonthlyFincance::where('dr_cr', 'CR')
                    ->where('dr_cr_code', 4000)
                    ->where('year', $year)
                    ->sum('cash_xe') ?? 0;

                $totalExpenditure = Treasury::where('dr_cr', 'DR')
                    ->where('year', $year)
                    ->sum('cash_xe') ?? 0;

                $totalBudget = Budget::where('year', $year)->sum('amount') ?? 0;

                $comparison[] = [
                    'year' => $year,
                    'revenue' => round($totalRevenue, 2),
                    'expenditure' => round($totalExpenditure, 2),
                    'budget' => round($totalBudget, 2),
                    'net' => round($totalRevenue - $totalExpenditure, 2),
                ];
            }

            return $comparison;
        } catch (\Exception $e) {
            Log::error('Error in getYearlyComparison: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get impress status - Latest year
     */
    private function getImpressStatus()
    {
        try {
            $latestYear = ImpressIssue::max('year') ?? date('Y');

            $issues = ImpressIssue::where('year', $latestYear)
                ->select('head', DB::raw('SUM(amount) as total_issue'))
                ->groupBy('head')
                ->get();

            $settlements = ImpressSettlement::where('year', $latestYear)
                ->select('head', DB::raw('SUM(amount) as total_settle'))
                ->groupBy('head')
                ->get();

            $status = [];
            $headMap = [];

            foreach ($issues as $issue) {
                $headMap[$issue->head]['issue'] = round($issue->total_issue, 2);
                $headMap[$issue->head]['head'] = $issue->head;
            }

            foreach ($settlements as $settlement) {
                $headMap[$settlement->head]['settle'] = round($settlement->total_settle, 2);
                if (!isset($headMap[$settlement->head]['head'])) {
                    $headMap[$settlement->head]['head'] = $settlement->head;
                }
            }

            foreach ($headMap as $head => $data) {
                $issue = $data['issue'] ?? 0;
                $settle = $data['settle'] ?? 0;
                $status[] = [
                    'head' => $head,
                    'issue' => $issue,
                    'settle' => $settle,
                    'balance' => round($issue - $settle, 2),
                    'status' => $issue > $settle ? 'Pending' : ($issue < $settle ? 'Over Settled' : 'Settled'),
                ];
            }

            // Sort by balance descending
            usort($status, function ($a, $b) {
                return $b['balance'] <=> $a['balance'];
            });

            return array_slice($status, 0, 10);
        } catch (\Exception $e) {
            Log::error('Error in getImpressStatus: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get supplementary summary - All time
     */
    private function getSupplementarySummary()
    {
        try {
            $supplementary = SupplementaryRecord::select(
                DB::raw('SUM(fr66p) as total_fr66p'),
                DB::raw('SUM(fr66m) as total_fr66m'),
                DB::raw('SUM(supplementary_amount) as total_supplementary'),
                DB::raw('COUNT(*) as total_records')
            )->first();

            // Get by head
            $byHead = SupplementaryRecord::select('head', DB::raw('SUM(supplementary_amount) as total'))
                ->groupBy('head')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get();

            return [
                'total_fr66p' => round($supplementary->total_fr66p ?? 0, 2),
                'total_fr66m' => round($supplementary->total_fr66m ?? 0, 2),
                'total_supplementary' => round($supplementary->total_supplementary ?? 0, 2),
                'total_records' => $supplementary->total_records ?? 0,
                'by_head' => $byHead->map(function ($item) {
                    return [
                        'head' => $item->head,
                        'total' => round($item->total, 2),
                    ];
                }),
            ];
        } catch (\Exception $e) {
            Log::error('Error in getSupplementarySummary: ' . $e->getMessage());
            return [
                'total_fr66p' => 0,
                'total_fr66m' => 0,
                'total_supplementary' => 0,
                'total_records' => 0,
                'by_head' => [],
            ];
        }
    }

    /**
     * Get quick stats - Current month only
     */
    private function getQuickStats()
    {
        try {
            $currentMonth = (int) date('m');
            $currentYear = (int) date('Y');

            // Current month revenue
            $currentRevenue = Treasury::where('dr_cr', 'CR')
                ->where('dr_cr_code', 4000)
                ->where('month', $currentMonth)
                ->where('year', $currentYear)
                ->sum('cash_xe') ?? 0;
            $currentRevenue += MonthlyFincance::where('dr_cr', 'CR')
                ->where('dr_cr_code', 4000)
                ->where('month', $currentMonth)
                ->where('year', $currentYear)
                ->sum('cash_xe') ?? 0;

            // Previous month revenue
            $prevMonth = $currentMonth - 1;
            $prevYear = $currentYear;
            if ($prevMonth < 1) {
                $prevMonth = 12;
                $prevYear = $currentYear - 1;
            }

            $prevRevenue = Treasury::where('dr_cr', 'CR')
                ->where('dr_cr_code', 4000)
                ->where('month', $prevMonth)
                ->where('year', $prevYear)
                ->sum('cash_xe') ?? 0;
            $prevRevenue += MonthlyFincance::where('dr_cr', 'CR')
                ->where('dr_cr_code', 4000)
                ->where('month', $prevMonth)
                ->where('year', $prevYear)
                ->sum('cash_xe') ?? 0;

            $revenueChange = $prevRevenue > 0 
                ? (($currentRevenue - $prevRevenue) / $prevRevenue) * 100 
                : 0;

            // Total unique heads
            $totalHeads = Treasury::distinct('head')->count('head');

            // Total unique TRNOs
            $totalTrnos = Treasury::distinct('trno')->count('trno');

            $monthName = $this->getMonthNames()[$currentMonth] ?? 'Month ' . $currentMonth;

            return [
                'current_month_revenue' => round($currentRevenue, 2),
                'previous_month_revenue' => round($prevRevenue, 2),
                'revenue_change_percentage' => round($revenueChange, 2),
                'total_heads' => $totalHeads,
                'total_trnos' => $totalTrnos,
                'current_month' => $currentMonth,
                'current_month_name' => $monthName,
                'total_budget_records' => Budget::count(),
                'total_estimate_records' => Estimate::count(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in getQuickStats: ' . $e->getMessage());
            return [
                'current_month_revenue' => 0,
                'previous_month_revenue' => 0,
                'revenue_change_percentage' => 0,
                'total_heads' => 0,
                'total_trnos' => 0,
                'current_month' => (int) date('m'),
                'current_month_name' => $this->getMonthNames()[(int) date('m')] ?? 'Unknown',
                'total_budget_records' => 0,
                'total_estimate_records' => 0,
            ];
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
     * Get chart colors
     */
    private function getChartColors()
    {
        return [
            '#3B82F6', // Blue
            '#10B981', // Green
            '#F59E0B', // Yellow
            '#EF4444', // Red
            '#8B5CF6', // Purple
            '#EC4899', // Pink
            '#14B8A6', // Teal
            '#F97316', // Orange
            '#6366F1', // Indigo
            '#84CC16', // Lime
            '#06B6D4', // Cyan
            '#D946EF', // Fuchsia
        ];
    }
}