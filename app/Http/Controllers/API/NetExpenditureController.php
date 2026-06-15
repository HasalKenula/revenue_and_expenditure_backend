<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\SupplementaryRecord;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NetExpenditureController extends Controller
{
    /**
     * Get net expenditure data with filters
     */
    public function getData(Request $request)
    {
        try {
            // Get filter values
            $head = $request->input('head');
            $program = $request->input('program');
            $project = $request->input('project');
            $month = $request->input('month');
            $selectedMonth = $month; // For cumulative calculations
            
            \Log::info('NetExpenditure getData called', ['head' => $head, 'program' => $program, 'project' => $project, 'month' => $month]);
            
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
            
            // Add month filter for cumulative data (month <= selected month)
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
            
            // ========== DEBIT (SAME DEPARTMENT) - SELECTED MONTH ONLY ==========
            $debitQuery = MonthlyFincance::query();
            
            if ($head) {
                $debitQuery->where('trno', $head);
                $debitQuery->where('head', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $debitQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $debitQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $debitQuery->where('month', $selectedMonth);
            }
            
            $debitQuery->where('dr_cr_code', '1000');
            $debitQuery->where('dr_cr', 'DR');
            
            $debitData = $debitQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_debit')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            $totalDebit = $debitQuery->sum('cash_xe');
            
            // ========== OTHER DEPARTMENT DEBIT - SELECTED MONTH ONLY ==========
            $otherDeptQuery = MonthlyFincance::query();
            
            if ($head) {
                $otherDeptQuery->where('trno', '!=', $head);
                $otherDeptQuery->where('head', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $otherDeptQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $otherDeptQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $otherDeptQuery->where('month', $selectedMonth);
            }
            
            $otherDeptQuery->where('dr_cr_code', '1000');
            $otherDeptQuery->where('dr_cr', 'DR');
            
            $otherDeptData = $otherDeptQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_other_dept_debit')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            $totalOtherDeptDebit = $otherDeptQuery->sum('cash_xe');
            
            // ========== SURCHARGE (SAME DEPARTMENT) - SELECTED MONTH ONLY ==========
            $surchargeQuery = MonthlyFincance::query();
            
            if ($head) {
                $surchargeQuery->where('trno', $head);
                $surchargeQuery->where('head', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $surchargeQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $surchargeQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $surchargeQuery->where('month', $selectedMonth);
            }
            
            $surchargeQuery->where('dr_cr_code', '2000');
            $surchargeQuery->where('dr_cr', 'CR');
            
            $surchargeData = $surchargeQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_surcharge')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            $totalSurcharge = $surchargeQuery->sum('cash_xe');
            
            // ========== OTHER DEPARTMENT SURCHARGE - SELECTED MONTH ONLY ==========
            $otherDeptSurchargeQuery = MonthlyFincance::query();
            
            if ($head) {
                $otherDeptSurchargeQuery->where('trno', '!=', $head);
                $otherDeptSurchargeQuery->where('head', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $otherDeptSurchargeQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $otherDeptSurchargeQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $otherDeptSurchargeQuery->where('month', $selectedMonth);
            }
            
            $otherDeptSurchargeQuery->where('dr_cr_code', '2000');
            $otherDeptSurchargeQuery->where('dr_cr', 'CR');
            
            $otherDeptSurchargeData = $otherDeptSurchargeQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_other_dept_surcharge')
            )
            ->whereNotNull('object')
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            $totalOtherDeptSurcharge = $otherDeptSurchargeQuery->sum('cash_xe');
            
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
            
            // Combine all data
            $records = [];
            $totalAllocation = 0;
            $totalFr66p = 0;
            $totalFr66m = 0;
            $totalSupplementary = 0;
            $totalNetAllocation = 0;
            $totalDebitAmount = 0;
            $totalOtherDeptDebitAmount = 0;
            $totalSurchargeAmount = 0;
            $totalOtherDeptSurchargeAmount = 0;
            $totalNetExpenditure = 0;
            $totalCumulativeExpenditureAmount = 0;
            $totalBalance = 0;
            
            foreach ($budgetRecords as $budget) {
                $key = $budget->object . '_' . ($budget->subproj ?? '');
                
                $fr66p = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66p : 0;
                $fr66m = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66m : 0;
                $supplementary = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_supplementary : 0;
                $debit = isset($debitData[$key]) ? $debitData[$key]->total_debit : 0;
                $otherDeptDebit = isset($otherDeptData[$key]) ? $otherDeptData[$key]->total_other_dept_debit : 0;
                $surcharge = isset($surchargeData[$key]) ? $surchargeData[$key]->total_surcharge : 0;
                $otherDeptSurcharge = isset($otherDeptSurchargeData[$key]) ? $otherDeptSurchargeData[$key]->total_other_dept_surcharge : 0;
                
                // Cumulative values for this object/subproject
                $cumulativeDebit = isset($cumulativeDebitData[$key]) ? $cumulativeDebitData[$key]->total_cumulative_debit : 0;
                $cumulativeOtherDeptDebit = isset($cumulativeOtherDeptData[$key]) ? $cumulativeOtherDeptData[$key]->total_cumulative_other_dept_debit : 0;
                $cumulativeSurcharge = isset($cumulativeSurchargeData[$key]) ? $cumulativeSurchargeData[$key]->total_cumulative_surcharge : 0;
                $cumulativeOtherDeptSurcharge = isset($cumulativeOtherDeptSurchargeData[$key]) ? $cumulativeOtherDeptSurchargeData[$key]->total_cumulative_other_dept_surcharge : 0;
                
                $netAllocation = ($budget->allocation ?? 0) + $fr66p - $fr66m + $supplementary;
                // Net Expenditure (Selected Month) = (Debit + Other Dept Debit) - (Surcharge + Other Dept Surcharge)
                $netExpenditure = ($debit + $otherDeptDebit) - ($surcharge + $otherDeptSurcharge);
                // Cumulative Expenditure = (Cumulative Debit + Cumulative Other Dept Debit) - (Cumulative Surcharge + Cumulative Other Dept Surcharge)
                $cumulativeExpenditure = ($cumulativeDebit + $cumulativeOtherDeptDebit) - ($cumulativeSurcharge + $cumulativeOtherDeptSurcharge);
                $balance = $netAllocation - $cumulativeExpenditure;
                
                $records[] = [
                    'object' => $budget->object,
                    'subproject' => $budget->subproj,
                    'objname' => $budget->objname,
                    'allocation' => round($budget->allocation ?? 0, 2),
                    'fr66p' => round($fr66p, 2),
                    'fr66m' => round($fr66m, 2),
                    'supplementary' => round($supplementary, 2),
                    'net_allocation' => round($netAllocation, 2),
                    'debit' => round($debit, 2),
                    'other_dept_debit' => round($otherDeptDebit, 2),
                    'surcharge' => round($surcharge, 2),
                    'other_dept_surcharge' => round($otherDeptSurcharge, 2),
                    'net_expenditure' => round($netExpenditure, 2),
                    'cumulative_expenditure' => round($cumulativeExpenditure, 2),
                    'balance' => round($balance, 2),
                ];
                
                $totalAllocation += $budget->allocation ?? 0;
                $totalFr66p += $fr66p;
                $totalFr66m += $fr66m;
                $totalSupplementary += $supplementary;
                $totalNetAllocation += $netAllocation;
                $totalDebitAmount += $debit;
                $totalOtherDeptDebitAmount += $otherDeptDebit;
                $totalSurchargeAmount += $surcharge;
                $totalOtherDeptSurchargeAmount += $otherDeptSurcharge;
                $totalNetExpenditure += $netExpenditure;
                $totalCumulativeExpenditureAmount += $cumulativeExpenditure;
                $totalBalance += $balance;
            }
            
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
                        'total_debit' => round($totalDebitAmount, 2),
                        'total_other_dept_debit' => round($totalOtherDeptDebitAmount, 2),
                        'total_surcharge' => round($totalSurchargeAmount, 2),
                        'total_other_dept_surcharge' => round($totalOtherDeptSurchargeAmount, 2),
                        'total_net_expenditure' => round($totalNetExpenditure, 2),
                        'total_cumulative_expenditure' => round($totalCumulativeExpenditureAmount, 2),
                        'total_balance' => round($totalBalance, 2),
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
     * Get filter options endpoint - handles both GET requests with query params
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
            
            // Get Debit data (Same Department - Selected Month)
            $debitQuery = MonthlyFincance::query();
            
            if ($head) {
                $debitQuery->where('trno', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $debitQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $debitQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $debitQuery->where('month', $selectedMonth);
            }
            $debitQuery->where('dr_cr_code', '1000');
            $debitQuery->where('dr_cr', 'DR');
            
            $debitData = $debitQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_debit')
            )
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            // Get Other Department Debit data (Selected Month)
            $otherDeptQuery = MonthlyFincance::query();
            
            if ($head) {
                $otherDeptQuery->where('trno', '!=', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $otherDeptQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $otherDeptQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $otherDeptQuery->where('month', $selectedMonth);
            }
            $otherDeptQuery->where('dr_cr_code', '1000');
            $otherDeptQuery->where('dr_cr', 'DR');
            
            $otherDeptData = $otherDeptQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_other_dept_debit')
            )
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            // Get Surcharge data (Same Department - Selected Month)
            $surchargeQuery = MonthlyFincance::query();
            
            if ($head) {
                $surchargeQuery->where('trno', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $surchargeQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $surchargeQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $surchargeQuery->where('month', $selectedMonth);
            }
            $surchargeQuery->where('dr_cr_code', '2000');
            $surchargeQuery->where('dr_cr', 'CR');
            
            $surchargeData = $surchargeQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_surcharge')
            )
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
            });
            
            // Get Other Department Surcharge data (Selected Month)
            $otherDeptSurchargeQuery = MonthlyFincance::query();
            
            if ($head) {
                $otherDeptSurchargeQuery->where('trno', '!=', $head);
            }
            if ($program) {
                $programPadded = str_pad($program, 2, '0', STR_PAD_LEFT);
                $otherDeptSurchargeQuery->where('program', $programPadded);
            }
            if ($project) {
                $projectPadded = str_pad($project, 2, '0', STR_PAD_LEFT);
                $otherDeptSurchargeQuery->where('project', $projectPadded);
            }
            if ($selectedMonth && $selectedMonth > 0) {
                $otherDeptSurchargeQuery->where('month', $selectedMonth);
            }
            $otherDeptSurchargeQuery->where('dr_cr_code', '2000');
            $otherDeptSurchargeQuery->where('dr_cr', 'CR');
            
            $otherDeptSurchargeData = $otherDeptSurchargeQuery->select(
                'object',
                'sub_project',
                DB::raw('SUM(cash_xe) as total_other_dept_surcharge')
            )
            ->groupBy('object', 'sub_project')
            ->get()
            ->keyBy(function ($item) {
                return $item->object . '_' . ($item->sub_project ?? '');
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
            
            // Prepare export data
            $exportData = [];
            
            foreach ($budgetRecords as $budget) {
                $key = $budget->object . '_' . ($budget->subproj ?? '');
                
                $fr66p = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66p : 0;
                $fr66m = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66m : 0;
                $supplementary = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_supplementary : 0;
                $debit = isset($debitData[$key]) ? $debitData[$key]->total_debit : 0;
                $otherDeptDebit = isset($otherDeptData[$key]) ? $otherDeptData[$key]->total_other_dept_debit : 0;
                $surcharge = isset($surchargeData[$key]) ? $surchargeData[$key]->total_surcharge : 0;
                $otherDeptSurcharge = isset($otherDeptSurchargeData[$key]) ? $otherDeptSurchargeData[$key]->total_other_dept_surcharge : 0;
                
                $cumulativeDebit = isset($cumulativeDebitData[$key]) ? $cumulativeDebitData[$key]->total_cumulative_debit : 0;
                $cumulativeOtherDeptDebit = isset($cumulativeOtherDeptData[$key]) ? $cumulativeOtherDeptData[$key]->total_cumulative_other_dept_debit : 0;
                $cumulativeSurcharge = isset($cumulativeSurchargeData[$key]) ? $cumulativeSurchargeData[$key]->total_cumulative_surcharge : 0;
                $cumulativeOtherDeptSurcharge = isset($cumulativeOtherDeptSurchargeData[$key]) ? $cumulativeOtherDeptSurchargeData[$key]->total_cumulative_other_dept_surcharge : 0;
                
                $netAllocation = ($budget->allocation ?? 0) + $fr66p - $fr66m + $supplementary;
                $netExpenditure = ($debit + $otherDeptDebit) - ($surcharge + $otherDeptSurcharge);
                $cumulativeExpenditure = ($cumulativeDebit + $cumulativeOtherDeptDebit) - ($cumulativeSurcharge + $cumulativeOtherDeptSurcharge);
                $balance = $netAllocation - $cumulativeExpenditure;
                
                $exportData[] = [
                    'Object Code' => $budget->object,
                    'Sub Project' => $budget->subproj ?? '-',
                    'Object Name' => $budget->objname ?? '-',
                    'Allocation' => round($budget->allocation ?? 0, 2),
                    'FR 66 P' => round($fr66p, 2),
                    'FR 66 M' => round($fr66m, 2),
                    'Supplementary' => round($supplementary, 2),
                    'Net Allocation' => round($netAllocation, 2),
                    'Debit (Same Dept)' => round($debit, 2),
                    'Debit (Other Dept)' => round($otherDeptDebit, 2),
                    'Surcharge (Same Dept)' => round($surcharge, 2),
                    'Surcharge (Other Dept)' => round($otherDeptSurcharge, 2),
                    'Net Expenditure' => round($netExpenditure, 2),
                    'Cumulative Expenditure' => round($cumulativeExpenditure, 2),
                    'Balance' => round($balance, 2),
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