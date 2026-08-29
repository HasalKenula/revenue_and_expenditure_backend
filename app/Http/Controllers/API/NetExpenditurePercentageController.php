<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\SupplementaryRecord;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NetExpenditurePercentageController extends Controller
{
    /**
     * Get net expenditure with percentage data
     */
    public function getData(Request $request)
    {
        try {
            $head = $request->input('head');
            $program = $request->input('program');
            $project = $request->input('project');
            $month = $request->input('month');
            $selectedMonth = $month;
            
            \Log::info('NetExpenditurePercentage getData called', [
                'head' => $head, 
                'program' => $program, 
                'project' => $project, 
                'month' => $month
            ]);
            
            // Build budget query
            $budgetQuery = Budget::query();
            
            if ($head) {
                $budgetQuery->where('head', $head);
            }
            if ($program) {
                $budgetQuery->where('program', $program);
            }
            if ($project) {
                $budgetQuery->where('project', $project);
            }
            
            // Get budget records grouped by object and subproj
            $budgetRecords = $budgetQuery->select(
                'object',
                'subproj',
                'objname',
                DB::raw('SUM(amount) as allocation')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'subproj', 'objname')
            ->orderBy('object')
            ->orderBy('subproj')
            ->get();
            
            \Log::info('Budget records found', ['count' => $budgetRecords->count()]);
            
            // Get supplementary data aggregated by object and subproject (Cumulative)
            $supplementaryQuery = SupplementaryRecord::query();
            
            if ($head) {
                $supplementaryQuery->where('head', $head);
            }
            if ($program) {
                $supplementaryQuery->where('program', $program);
            }
            if ($project) {
                $supplementaryQuery->where('project', $project);
            }
            
            if ($selectedMonth && $selectedMonth > 0) {
                $supplementaryQuery->where('month', '<=', $selectedMonth);
            }
            
            $supplementaryData = $supplementaryQuery->select(
                'object',
                'subproject',
                DB::raw('SUM(fr66p) as total_fr66p'),
                DB::raw('SUM(fr66m) as total_fr66m'),
                DB::raw('SUM(supplementary_amount) as total_supplementary')
            )
            ->groupBy('object', 'subproject')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->subproject ?? '');
            });
            
            // ========== CUMULATIVE DATA (Month 1 to Selected Month) ==========
            
            // Cumulative Debit Data (Same Department)
            $cumulativeDebitQuery = MonthlyFincance::query();
            
            if ($head) {
                $cumulativeDebitQuery->where('trno', $head);
                $cumulativeDebitQuery->where('head', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $cumulativeDebitQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $cumulativeDebitQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $cumulativeDebitQuery->where('month', '<=', $selectedMonth);
            }
            
            $cumulativeDebitQuery->where('dr_cr_code', '1000');
            $cumulativeDebitQuery->where('dr_cr', 'DR');
            
            $cumulativeDebitData = $cumulativeDebitQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_cumulative_debit')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            $totalCumulativeDebit = $cumulativeDebitQuery->sum('cash_xe');
            
            // Cumulative Other Department Debit Data
            $cumulativeOtherDeptQuery = MonthlyFincance::query();
            
            if ($head) {
                $cumulativeOtherDeptQuery->where('trno', '!=', $head);
                $cumulativeOtherDeptQuery->where('head', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $cumulativeOtherDeptQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $cumulativeOtherDeptQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $cumulativeOtherDeptQuery->where('month', '<=', $selectedMonth);
            }
            
            $cumulativeOtherDeptQuery->where('dr_cr_code', '1000');
            $cumulativeOtherDeptQuery->where('dr_cr', 'DR');
            
            $cumulativeOtherDeptData = $cumulativeOtherDeptQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_cumulative_other_dept_debit')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            $totalCumulativeOtherDeptDebit = $cumulativeOtherDeptQuery->sum('cash_xe');
            
            // Cumulative Surcharge Data (Same Department)
            $cumulativeSurchargeQuery = MonthlyFincance::query();
            
            if ($head) {
                $cumulativeSurchargeQuery->where('trno', $head);
                $cumulativeSurchargeQuery->where('head', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $cumulativeSurchargeQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $cumulativeSurchargeQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $cumulativeSurchargeQuery->where('month', '<=', $selectedMonth);
            }
            
            $cumulativeSurchargeQuery->where('dr_cr_code', '2000');
            $cumulativeSurchargeQuery->where('dr_cr', 'CR');
            
            $cumulativeSurchargeData = $cumulativeSurchargeQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_cumulative_surcharge')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            $totalCumulativeSurcharge = $cumulativeSurchargeQuery->sum('cash_xe');
            
            // Cumulative Other Department Surcharge Data
            $cumulativeOtherDeptSurchargeQuery = MonthlyFincance::query();
            
            if ($head) {
                $cumulativeOtherDeptSurchargeQuery->where('trno', '!=', $head);
                $cumulativeOtherDeptSurchargeQuery->where('head', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $cumulativeOtherDeptSurchargeQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $cumulativeOtherDeptSurchargeQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $cumulativeOtherDeptSurchargeQuery->where('month', '<=', $selectedMonth);
            }
            
            $cumulativeOtherDeptSurchargeQuery->where('dr_cr_code', '2000');
            $cumulativeOtherDeptSurchargeQuery->where('dr_cr', 'CR');
            
            $cumulativeOtherDeptSurchargeData = $cumulativeOtherDeptSurchargeQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_cumulative_other_dept_surcharge')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            $totalCumulativeOtherDeptSurcharge = $cumulativeOtherDeptSurchargeQuery->sum('cash_xe');
            
            // Calculate cumulative expenditure totals
            $totalCumulativeExpenditure = $totalCumulativeDebit + $totalCumulativeOtherDeptDebit - $totalCumulativeSurcharge - $totalCumulativeOtherDeptSurcharge;
            
            // Calculate Expected Percentage based on selected month
            $expectedPercentage = $selectedMonth > 0 ? (100 * $selectedMonth / 12) : 0;
            
            // Combine all data
            $records = [];
            $totalAllocation = 0;
            $totalFr66p = 0;
            $totalFr66m = 0;
            $totalSupplementary = 0;
            $totalNetAllocation = 0;
            $totalCumulativeExpenditureAmount = 0;
            $totalBalance = 0;
            
            foreach ($budgetRecords as $budget) {
                $key = $budget->object . '_' . ($budget->subproj ?? '');
                
                $fr66p = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66p : 0;
                $fr66m = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66m : 0;
                $supplementary = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_supplementary : 0;
                
                $cumulativeDebit = isset($cumulativeDebitData[$key]) ? $cumulativeDebitData[$key]->total_cumulative_debit : 0;
                $cumulativeOtherDeptDebit = isset($cumulativeOtherDeptData[$key]) ? $cumulativeOtherDeptData[$key]->total_cumulative_other_dept_debit : 0;
                $cumulativeSurcharge = isset($cumulativeSurchargeData[$key]) ? $cumulativeSurchargeData[$key]->total_cumulative_surcharge : 0;
                $cumulativeOtherDeptSurcharge = isset($cumulativeOtherDeptSurchargeData[$key]) ? $cumulativeOtherDeptSurchargeData[$key]->total_cumulative_other_dept_surcharge : 0;
                
                $allocation = $budget->allocation ?? 0;
                $netAllocation = $allocation + $fr66p - $fr66m + $supplementary;
                $cumulativeExpenditure = ($cumulativeDebit + $cumulativeOtherDeptDebit) - ($cumulativeSurcharge + $cumulativeOtherDeptSurcharge);
                $balance = $netAllocation - $cumulativeExpenditure;
                
                // Calculate percentages (removed % with Allocation and Perfect % with Allocation)
                $percentageWithNetAllocation = $netAllocation > 0 ? ($cumulativeExpenditure / $netAllocation) * 100 : 0;
                $perfectPercentageWithNetAllocation = $selectedMonth > 0 ? ($netAllocation / 12) * $selectedMonth : 0;
                $expectedPercentageValue = $expectedPercentage;
                
                $records[] = [
                    'object' => $budget->object,
                    'subproject' => $budget->subproj,
                    'objname' => $budget->objname,
                    'allocation' => round($allocation, 2),
                    'fr66p' => round($fr66p, 2),
                    'fr66m' => round($fr66m, 2),
                    'supplementary' => round($supplementary, 2),
                    'net_allocation' => round($netAllocation, 2),
                    'cumulative_expenditure' => round($cumulativeExpenditure, 2),
                    'balance' => round($balance, 2),
                    'percentage_with_net_allocation' => round($percentageWithNetAllocation, 2),
                    'perfect_percentage_with_net_allocation' => round($perfectPercentageWithNetAllocation, 2),
                    'expected_percentage' => round($expectedPercentageValue, 2),
                ];
                
                $totalAllocation += $allocation;
                $totalFr66p += $fr66p;
                $totalFr66m += $fr66m;
                $totalSupplementary += $supplementary;
                $totalNetAllocation += $netAllocation;
                $totalCumulativeExpenditureAmount += $cumulativeExpenditure;
                $totalBalance += $balance;
            }
            
            // Calculate total percentages
            $totalPercentageWithNetAllocation = $totalNetAllocation > 0 ? ($totalCumulativeExpenditureAmount / $totalNetAllocation) * 100 : 0;
            $totalPerfectPercentageWithNetAllocation = $selectedMonth > 0 ? ($totalNetAllocation / 12) * $selectedMonth : 0;
            $totalExpectedPercentage = $expectedPercentage;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $records,
                    'totals' => [
                        'total_allocation' => round($totalAllocation, 2),
                        'total_fr66p' => round($totalFr66p, 2),
                        'total_fr66m' => round($totalFr66m, 2),
                        'total_supplementary' => round($totalSupplementary, 2),
                        'total_net_allocation' => round($totalNetAllocation, 2),
                        'total_cumulative_expenditure' => round($totalCumulativeExpenditureAmount, 2),
                        'total_balance' => round($totalBalance, 2),
                        'total_percentage_with_net_allocation' => round($totalPercentageWithNetAllocation, 2),
                        'total_perfect_percentage_with_net_allocation' => round($totalPerfectPercentageWithNetAllocation, 2),
                        'total_expected_percentage' => round($totalExpectedPercentage, 2),
                    ],
                    'applied_filters' => [
                        'head' => $head,
                        'program' => $program,
                        'project' => $project,
                        'month' => $month,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getData: ' . $e->getMessage());
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
    public function getFilterOptionsEndpoint(Request $request)
    {
        try {
            $selectedHead = $request->input('head');
            $selectedProgram = $request->input('program');
            $selectedProject = $request->input('project');
            
            // Get unique heads (trno) from MonthlyFincance
            $heads = MonthlyFincance::whereNotNull('trno')
                ->distinct()
                ->orderBy('trno')
                ->pluck('trno')
                ->values();
            
            // Get programs based on selected head
            $programsQuery = Budget::whereNotNull('program');
            if ($selectedHead) {
                $programsQuery->where('head', $selectedHead);
            }
            $programs = $programsQuery->distinct()->orderBy('program')->pluck('program')->values();
            
            // Get projects based on selected head and program
            $projectsQuery = Budget::whereNotNull('project');
            if ($selectedHead) {
                $projectsQuery->where('head', $selectedHead);
            }
            if ($selectedProgram) {
                $projectsQuery->where('program', $selectedProgram);
            }
            $projects = $projectsQuery->distinct()->orderBy('project')->pluck('project')->values();
            
            // Get available months from monthly_fincances
            $months = MonthlyFincance::whereNotNull('month')
                ->distinct()
                ->orderBy('month')
                ->pluck('month')
                ->values();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'heads' => $heads,
                    'programs' => $programs,
                    'projects' => $projects,
                    'months' => $months,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getFilterOptionsEndpoint: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [
                    'heads' => [],
                    'programs' => [],
                    'projects' => [],
                    'months' => [],
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
            $head = $request->input('head');
            $program = $request->input('program');
            $project = $request->input('project');
            $month = $request->input('month');
            $selectedMonth = $month;
            
            // Build budget query
            $budgetQuery = Budget::query();
            
            if ($head) {
                $budgetQuery->where('head', $head);
            }
            if ($program) {
                $budgetQuery->where('program', $program);
            }
            if ($project) {
                $budgetQuery->where('project', $project);
            }
            
            $budgetRecords = $budgetQuery->select(
                'object',
                'subproj',
                'objname',
                DB::raw('SUM(amount) as allocation')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'subproj', 'objname')
            ->orderBy('object')
            ->orderBy('subproj')
            ->get();
            
            // Get supplementary data with cumulative month filter
            $supplementaryQuery = SupplementaryRecord::query();
            
            if ($head) {
                $supplementaryQuery->where('head', $head);
            }
            if ($program) {
                $supplementaryQuery->where('program', $program);
            }
            if ($project) {
                $supplementaryQuery->where('project', $project);
            }
            
            if ($selectedMonth && $selectedMonth > 0) {
                $supplementaryQuery->where('month', '<=', $selectedMonth);
            }
            
            $supplementaryData = $supplementaryQuery->select(
                'object',
                'subproject',
                DB::raw('SUM(fr66p) as total_fr66p'),
                DB::raw('SUM(fr66m) as total_fr66m'),
                DB::raw('SUM(supplementary_amount) as total_supplementary')
            )
            ->groupBy('object', 'subproject')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->subproject ?? '');
            });
            
            // Get Cumulative Debit Data
            $cumulativeDebitQuery = MonthlyFincance::query();
            if ($head) {
                $cumulativeDebitQuery->where('trno', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $cumulativeDebitQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $cumulativeDebitQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $cumulativeDebitQuery->where('month', '<=', $selectedMonth);
            }
            $cumulativeDebitQuery->where('dr_cr_code', '1000');
            $cumulativeDebitQuery->where('dr_cr', 'DR');
            
            $cumulativeDebitData = $cumulativeDebitQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_cumulative_debit')
            )
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            // Get Cumulative Other Department Debit Data
            $cumulativeOtherDeptQuery = MonthlyFincance::query();
            if ($head) {
                $cumulativeOtherDeptQuery->where('trno', '!=', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $cumulativeOtherDeptQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $cumulativeOtherDeptQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $cumulativeOtherDeptQuery->where('month', '<=', $selectedMonth);
            }
            $cumulativeOtherDeptQuery->where('dr_cr_code', '1000');
            $cumulativeOtherDeptQuery->where('dr_cr', 'DR');
            
            $cumulativeOtherDeptData = $cumulativeOtherDeptQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_cumulative_other_dept_debit')
            )
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            // Get Cumulative Surcharge Data
            $cumulativeSurchargeQuery = MonthlyFincance::query();
            if ($head) {
                $cumulativeSurchargeQuery->where('trno', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $cumulativeSurchargeQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $cumulativeSurchargeQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $cumulativeSurchargeQuery->where('month', '<=', $selectedMonth);
            }
            $cumulativeSurchargeQuery->where('dr_cr_code', '2000');
            $cumulativeSurchargeQuery->where('dr_cr', 'CR');
            
            $cumulativeSurchargeData = $cumulativeSurchargeQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_cumulative_surcharge')
            )
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            // Get Cumulative Other Department Surcharge Data
            $cumulativeOtherDeptSurchargeQuery = MonthlyFincance::query();
            if ($head) {
                $cumulativeOtherDeptSurchargeQuery->where('trno', '!=', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $cumulativeOtherDeptSurchargeQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $cumulativeOtherDeptSurchargeQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $cumulativeOtherDeptSurchargeQuery->where('month', '<=', $selectedMonth);
            }
            $cumulativeOtherDeptSurchargeQuery->where('dr_cr_code', '2000');
            $cumulativeOtherDeptSurchargeQuery->where('dr_cr', 'CR');
            
            $cumulativeOtherDeptSurchargeData = $cumulativeOtherDeptSurchargeQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_cumulative_other_dept_surcharge')
            )
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            // Calculate Expected Percentage
            $expectedPercentage = $selectedMonth > 0 ? (100 * $selectedMonth / 12) : 0;
            
            // Prepare export data
            $exportData = [];
            
            foreach ($budgetRecords as $budget) {
                $key = $budget->object . '_' . ($budget->subproj ?? '');
                
                $fr66p = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66p : 0;
                $fr66m = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66m : 0;
                $supplementary = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_supplementary : 0;
                
                $cumulativeDebit = isset($cumulativeDebitData[$key]) ? $cumulativeDebitData[$key]->total_cumulative_debit : 0;
                $cumulativeOtherDeptDebit = isset($cumulativeOtherDeptData[$key]) ? $cumulativeOtherDeptData[$key]->total_cumulative_other_dept_debit : 0;
                $cumulativeSurcharge = isset($cumulativeSurchargeData[$key]) ? $cumulativeSurchargeData[$key]->total_cumulative_surcharge : 0;
                $cumulativeOtherDeptSurcharge = isset($cumulativeOtherDeptSurchargeData[$key]) ? $cumulativeOtherDeptSurchargeData[$key]->total_cumulative_other_dept_surcharge : 0;
                
                $allocation = $budget->allocation ?? 0;
                $netAllocation = $allocation + $fr66p - $fr66m + $supplementary;
                $cumulativeExpenditure = ($cumulativeDebit + $cumulativeOtherDeptDebit) - ($cumulativeSurcharge + $cumulativeOtherDeptSurcharge);
                $balance = $netAllocation - $cumulativeExpenditure;
                
                // Calculate percentages (removed % with Allocation and Perfect % with Allocation)
                $percentageWithNetAllocation = $netAllocation > 0 ? ($cumulativeExpenditure / $netAllocation) * 100 : 0;
                $perfectPercentageWithNetAllocation = $selectedMonth > 0 ? ($netAllocation / 12) * $selectedMonth : 0;
                
                $exportData[] = [
                    'Object Code' => $budget->object,
                    'Sub Project' => $budget->subproj ?? '-',
                    'Allocation' => round($allocation, 2),
                    'FR66P' => round($fr66p, 2),
                    'FR66M' => round($fr66m, 2),
                    'Supplementary' => round($supplementary, 2),
                    'Net Allocation' => round($netAllocation, 2),
                    'Cumulative Expenditure' => round($cumulativeExpenditure, 2),
                    'Balance' => round($balance, 2),
                    '% with Net Allocation' => round($percentageWithNetAllocation, 2),
                    'Perfect % with Net Allocation' => round($perfectPercentageWithNetAllocation, 2),
                    'Expected %' => round($expectedPercentage, 2),
                ];
            }
            
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