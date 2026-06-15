<?php

// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use App\Models\Budget;
// use App\Models\SupplementaryRecord;
// use App\Models\MonthlyFincance;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;

// class NetAllocationController extends Controller
// {
   
//     public function getData(Request $request)
//     {
//         try {
//             // Get filter values
//             $head = $request->input('head');
//             $program = $request->input('program');
//             $project = $request->input('project');
//             $month = $request->input('month'); // Add month filter
            
//             \Log::info('NetExpenditure getData called', ['head' => $head, 'program' => $program, 'project' => $project, 'month' => $month]);
            
//             // Build budget query
//             $budgetQuery = Budget::query();
            
//             if ($head) {
//                 $budgetQuery->where('head', $head);
//             }
//             if ($program) {
//                 $budgetQuery->where('program', $program);
//             }
//             if ($project) {
//                 $budgetQuery->where('project', $project);
//             }
            
//             // Get budget records grouped by object and subproj
//             $budgetRecords = $budgetQuery->select(
//                 'object',
//                 'subproj',
//                 'objname',
//                 DB::raw('SUM(amount) as allocation')
//             )
//             ->whereNotNull('object')
//             ->groupBy('object', 'subproj', 'objname')
//             ->orderBy('object')
//             ->orderBy('subproj')
//             ->get();
            
//             \Log::info('Budget records found', ['count' => $budgetRecords->count()]);
            
//             // Get supplementary data aggregated by object and subproject
//             // If month is selected, get cumulative from month 1 to selected month
//             $supplementaryQuery = SupplementaryRecord::query();
            
//             if ($head) {
//                 $supplementaryQuery->where('head', $head);
//             }
//             if ($program) {
//                 $supplementaryQuery->where('program', $program);
//             }
//             if ($project) {
//                 $supplementaryQuery->where('project', $project);
//             }
            
//             // Add month filter for cumulative data (month <= selected month)
//             if ($month && $month > 0) {
//                 $supplementaryQuery->where('month', '<=', $month);
//             }
            
//             $supplementaryData = $supplementaryQuery->select(
//                 'object',
//                 'subproject',
//                 DB::raw('SUM(fr66p) as total_fr66p'),
//                 DB::raw('SUM(fr66m) as total_fr66m'),
//                 DB::raw('SUM(supplementary_amount) as total_supplementary')
//             )
//             ->groupBy('object', 'subproject')
//             ->get()
//             ->keyBy(function ($item) {
//                 return $item->object . '_' . ($item->subproject ?? '');
//             });
            
//             // Combine budget and supplementary data
//             $records = [];
//             $totalAllocation = 0;
//             $totalFr66p = 0;
//             $totalFr66m = 0;
//             $totalSupplementary = 0;
//             $totalNetAllocation = 0;
            
//             foreach ($budgetRecords as $budget) {
//                 $key = $budget->object . '_' . ($budget->subproj ?? '');
                
//                 $fr66p = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66p : 0;
//                 $fr66m = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66m : 0;
//                 $supplementary = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_supplementary : 0;
                
//                 $netAllocation = ($budget->allocation ?? 0) + $fr66p - $fr66m + $supplementary;
                
//                 $records[] = [
//                     'object' => $budget->object,
//                     'subproject' => $budget->subproj,
//                     'objname' => $budget->objname,
//                     'allocation' => round($budget->allocation ?? 0, 2),
//                     'fr66p' => round($fr66p, 2),
//                     'fr66m' => round($fr66m, 2),
//                     'supplementary' => round($supplementary, 2),
//                     'net_allocation' => round($netAllocation, 2),
//                 ];
                
//                 $totalAllocation += $budget->allocation ?? 0;
//                 $totalFr66p += $fr66p;
//                 $totalFr66m += $fr66m;
//                 $totalSupplementary += $supplementary;
//                 $totalNetAllocation += $netAllocation;
//             }
            
//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'records' => $records,
//                     'totals' => [
//                         'total_allocation' => round($totalAllocation, 2),
//                         'total_fr66p' => round($totalFr66p, 2),
//                         'total_fr66m' => round($totalFr66m, 2),
//                         'total_supplementary' => round($totalSupplementary, 2),
//                         'total_net_allocation' => round($totalNetAllocation, 2),
//                     ],
//                     'applied_filters' => [
//                         'head' => $head,
//                         'program' => $program,
//                         'project' => $project,
//                         'month' => $month,
//                     ]
//                 ]
//             ]);
//         } catch (\Exception $e) {
//             \Log::error('Error in getData: ' . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage(),
//                 'line' => $e->getLine()
//             ], 500);
//         }
//     }
    
//     /**
//      * Get filter options endpoint - handles both GET requests with query params
//      */
//     public function getFilterOptionsEndpoint(Request $request)
//     {
//         try {
//             $selectedHead = $request->input('head');
//             $selectedProgram = $request->input('program');
//             $selectedProject = $request->input('project');
            
//             // Get unique heads (trno) from MonthlyFincance
//             $heads = MonthlyFincance::whereNotNull('trno')
//                 ->distinct()
//                 ->orderBy('trno')
//                 ->pluck('trno')
//                 ->values();
            
//             // Get programs based on selected head
//             $programsQuery = Budget::whereNotNull('program');
//             if ($selectedHead) {
//                 $programsQuery->where('head', $selectedHead);
//             }
//             $programs = $programsQuery->distinct()->orderBy('program')->pluck('program')->values();
            
//             // Get projects based on selected head and program
//             $projectsQuery = Budget::whereNotNull('project');
//             if ($selectedHead) {
//                 $projectsQuery->where('head', $selectedHead);
//             }
//             if ($selectedProgram) {
//                 $projectsQuery->where('program', $selectedProgram);
//             }
//             $projects = $projectsQuery->distinct()->orderBy('project')->pluck('project')->values();
            
//             // Get available months from supplementary records
//             $months = MonthlyFincance::whereNotNull('month')
//                 ->distinct()
//                 ->orderBy('month')
//                 ->pluck('month')
//                 ->values();
            
//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'heads' => $heads,
//                     'programs' => $programs,
//                     'projects' => $projects,
//                     'months' => $months,
//                 ]
//             ]);
//         } catch (\Exception $e) {
//             \Log::error('Error in getFilterOptionsEndpoint: ' . $e->getMessage());
//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'heads' => [],
//                     'programs' => [],
//                     'projects' => [],
//                     'months' => [],
//                 ]
//             ]);
//         }
//     }
    
   
// }




namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\SupplementaryRecord;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NetAllocationController extends Controller
{
    /**
     * Get net allocation data with filters
     */
    public function getData(Request $request)
    {
        try {
            // Get filter values
            $head = $request->input('head');
            $program = $request->input('program');
            $project = $request->input('project');
            $month = $request->input('month');
            $viewType = $request->input('view_type', 'detailed'); // 'detailed' or 'object_wise'
            
            \Log::info('NetAllocation getData called', [
                'head' => $head, 
                'program' => $program, 
                'project' => $project, 
                'month' => $month,
                'view_type' => $viewType
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
            
            // Check if we need object-wise view (only month filter without head/program/project)
            $isObjectWiseView = $viewType === 'object_wise' || 
                                ($month && !$head && !$program && !$project);
            
            if ($isObjectWiseView) {
                // OBJECT-WISE VIEW: Group by object only (ignore subproject)
                $budgetRecords = $budgetQuery->select(
                    'object',
                    DB::raw('MAX(objname) as objname'),
                    DB::raw('SUM(amount) as allocation')
                )
                ->whereNotNull('object')
                ->groupBy('object')
                ->orderBy('object')
                ->get();
                
                \Log::info('Object-wise budget records found', ['count' => $budgetRecords->count()]);
                
                // Get supplementary data grouped by object only
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
                if ($month && $month > 0) {
                    $supplementaryQuery->where('month', '<=', $month);
                }
                
                $supplementaryData = $supplementaryQuery->select(
                    'object',
                    DB::raw('SUM(fr66p) as total_fr66p'),
                    DB::raw('SUM(fr66m) as total_fr66m'),
                    DB::raw('SUM(supplementary_amount) as total_supplementary')
                )
                ->whereNotNull('object')
                ->groupBy('object')
                ->get()
                ->keyBy('object');
                
                // Combine data for object-wise view
                $records = [];
                $totalAllocation = 0;
                $totalFr66p = 0;
                $totalFr66m = 0;
                $totalSupplementary = 0;
                $totalNetAllocation = 0;
                
                foreach ($budgetRecords as $budget) {
                    $fr66p = isset($supplementaryData[$budget->object]) ? $supplementaryData[$budget->object]->total_fr66p : 0;
                    $fr66m = isset($supplementaryData[$budget->object]) ? $supplementaryData[$budget->object]->total_fr66m : 0;
                    $supplementary = isset($supplementaryData[$budget->object]) ? $supplementaryData[$budget->object]->total_supplementary : 0;
                    
                    $netAllocation = ($budget->allocation ?? 0) + $fr66p - $fr66m + $supplementary;
                    
                    $records[] = [
                        'object' => $budget->object,
                        'subproject' => null,
                        'objname' => $budget->objname,
                        'allocation' => round($budget->allocation ?? 0, 2),
                        'fr66p' => round($fr66p, 2),
                        'fr66m' => round($fr66m, 2),
                        'supplementary' => round($supplementary, 2),
                        'net_allocation' => round($netAllocation, 2),
                    ];
                    
                    $totalAllocation += $budget->allocation ?? 0;
                    $totalFr66p += $fr66p;
                    $totalFr66m += $fr66m;
                    $totalSupplementary += $supplementary;
                    $totalNetAllocation += $netAllocation;
                }
                
            } else {
                // DETAILED VIEW: Group by object and subproject
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
                
                \Log::info('Detailed budget records found', ['count' => $budgetRecords->count()]);
                
                // Get supplementary data grouped by object and subproject
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
                if ($month && $month > 0) {
                    $supplementaryQuery->where('month', '<=', $month);
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
                
                // Combine data for detailed view
                $records = [];
                $totalAllocation = 0;
                $totalFr66p = 0;
                $totalFr66m = 0;
                $totalSupplementary = 0;
                $totalNetAllocation = 0;
                
                foreach ($budgetRecords as $budget) {
                    $key = $budget->object . '_' . ($budget->subproj ?? '');
                    
                    $fr66p = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66p : 0;
                    $fr66m = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66m : 0;
                    $supplementary = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_supplementary : 0;
                    
                    $netAllocation = ($budget->allocation ?? 0) + $fr66p - $fr66m + $supplementary;
                    
                    $records[] = [
                        'object' => $budget->object,
                        'subproject' => $budget->subproj,
                        'objname' => $budget->objname,
                        'allocation' => round($budget->allocation ?? 0, 2),
                        'fr66p' => round($fr66p, 2),
                        'fr66m' => round($fr66m, 2),
                        'supplementary' => round($supplementary, 2),
                        'net_allocation' => round($netAllocation, 2),
                    ];
                    
                    $totalAllocation += $budget->allocation ?? 0;
                    $totalFr66p += $fr66p;
                    $totalFr66m += $fr66m;
                    $totalSupplementary += $supplementary;
                    $totalNetAllocation += $netAllocation;
                }
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
                    ],
                    'applied_filters' => [
                        'head' => $head,
                        'program' => $program,
                        'project' => $project,
                        'month' => $month,
                    ],
                    'view_type' => $isObjectWiseView ? 'object_wise' : 'detailed'
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
            
            // Get available months (all 12 months)
            $months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
            
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
                    'months' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                ]
            ]);
        }
    }
    
    /**
     * Export data to CSV
     */
    public function export(Request $request)
    {
        try {
            $head = $request->input('head');
            $program = $request->input('program');
            $project = $request->input('project');
            $month = $request->input('month');
            $viewType = $request->input('view_type', 'detailed');
            
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
            
            $isObjectWiseView = $viewType === 'object_wise' || 
                                ($month && !$head && !$program && !$project);
            
            if ($isObjectWiseView) {
                $budgetRecords = $budgetQuery->select(
                    'object',
                    DB::raw('MAX(objname) as objname'),
                    DB::raw('SUM(amount) as allocation')
                )
                ->whereNotNull('object')
                ->groupBy('object')
                ->orderBy('object')
                ->get();
                
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
                
                if ($month && $month > 0) {
                    $supplementaryQuery->where('month', '<=', $month);
                }
                
                $supplementaryData = $supplementaryQuery->select(
                    'object',
                    DB::raw('SUM(fr66p) as total_fr66p'),
                    DB::raw('SUM(fr66m) as total_fr66m'),
                    DB::raw('SUM(supplementary_amount) as total_supplementary')
                )
                ->whereNotNull('object')
                ->groupBy('object')
                ->get()
                ->keyBy('object');
                
                $exportData = [];
                foreach ($budgetRecords as $budget) {
                    $fr66p = isset($supplementaryData[$budget->object]) ? $supplementaryData[$budget->object]->total_fr66p : 0;
                    $fr66m = isset($supplementaryData[$budget->object]) ? $supplementaryData[$budget->object]->total_fr66m : 0;
                    $supplementary = isset($supplementaryData[$budget->object]) ? $supplementaryData[$budget->object]->total_supplementary : 0;
                    $netAllocation = ($budget->allocation ?? 0) + $fr66p - $fr66m + $supplementary;
                    
                    $exportData[] = [
                        'Object Code' => $budget->object,
                        'Object Name' => $budget->objname ?? '-',
                        'Allocation (Rs)' => round($budget->allocation ?? 0, 2),
                        'FR 66 P (Rs)' => round($fr66p, 2),
                        'FR 66 M (Rs)' => round($fr66m, 2),
                        'Supplementary (Rs)' => round($supplementary, 2),
                        'Net Allocation (Rs)' => round($netAllocation, 2),
                    ];
                }
            } else {
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
                
                if ($month && $month > 0) {
                    $supplementaryQuery->where('month', '<=', $month);
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
                
                $exportData = [];
                foreach ($budgetRecords as $budget) {
                    $key = $budget->object . '_' . ($budget->subproj ?? '');
                    
                    $fr66p = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66p : 0;
                    $fr66m = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_fr66m : 0;
                    $supplementary = isset($supplementaryData[$key]) ? $supplementaryData[$key]->total_supplementary : 0;
                    $netAllocation = ($budget->allocation ?? 0) + $fr66p - $fr66m + $supplementary;
                    
                    $exportData[] = [
                        'Object Code' => $budget->object,
                        'Sub Project' => $budget->subproj ?? '-',
                        'Object Name' => $budget->objname ?? '-',
                        'Allocation (Rs)' => round($budget->allocation ?? 0, 2),
                        'FR 66 P (Rs)' => round($fr66p, 2),
                        'FR 66 M (Rs)' => round($fr66m, 2),
                        'Supplementary (Rs)' => round($supplementary, 2),
                        'Net Allocation (Rs)' => round($netAllocation, 2),
                    ];
                }
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