<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpkeepController extends Controller
{
    /**
     * Get Upkeep (Education) Report data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $viewType = $request->input('view_type', 'cumulative');
            
            \Log::info('Upkeep getData called', [
                'year' => $year,
                'month' => $month,
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

            // Determine months to include based on view type
            if ($viewType === 'cumulative') {
                $monthsToInclude = range(1, (int)$month);
            } else {
                $monthsToInclude = [(int)$month];
            }

            // ========== UPKEEP REPORT ROWS (TRNO = 310) ==========
            $upkeepRows = [
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1305],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1305]
            ];

            // Get subject names from budgets table
            $upkeepRows = $this->addSubjectNames($upkeepRows);

            // Process the data
            $results = $this->processRows($upkeepRows, $year, $monthsToInclude);

            // Get month names for display
            $monthNames = $this->getMonthNames();
            $monthNamesToShow = [];
            foreach ($monthsToInclude as $monthNum) {
                $monthNamesToShow[$monthNum] = $monthNames[$monthNum];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'upkeep_report' => $results,
                    'months' => $monthsToInclude,
                    'month_names' => $monthNamesToShow,
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                        'view_type' => $viewType
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in Upkeep getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Add subject names from budgets table to rows
     */
    private function addSubjectNames($rows)
    {
        foreach ($rows as &$row) {
            // Query the budgets table to get objname (subject name)
            $budget = Budget::where('head', $row['trno'])
                ->where('program', $row['program'])
                ->where('project', $row['project'])
                ->where('subproj', $row['sub_project'])
                ->where('object', $row['object'])
                ->first();

            // Get subject name from budget, fallback to default if not found
            $row['subject_name'] = $budget ? $budget->objname : 'Unknown';
        }

        return $rows;
    }

    /**
     * Process rows for Upkeep report
     */
    private function processRows($rows, $year, $monthsToInclude)
    {
        $results = [];
        $grandTotalAllocation = 0;
        $grandTotalExpenditure = 0;
        $grandTotalBalance = 0;

        foreach ($rows as $row) {
            // Get Allocation from Budget table
            $allocation = Budget::where('head', $row['trno'])
                ->where('program', $row['program'])
                ->where('project', $row['project'])
                ->where('subproj', $row['sub_project'])
                ->where('object', $row['object'])
                ->sum('amount');

            $cumulativeExpenditure = 0;

            foreach ($monthsToInclude as $currentMonth) {
                // ========== DEBIT (trno == head) ==========
                // Get DR amount (code 1000)
                $debitDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 1000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get CR amount (code 2000)
                $debitCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 2000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Net Debit = DR - CR
                $netDebit = $debitDR - $debitCR;

                // ========== OTHER DEBIT (trno != head) ==========
                // Get DR amount (code 1000)
                $otherDebitDR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 1000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get CR amount (code 2000)
                $otherDebitCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 2000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Net Other Debit = DR - CR
                $netOtherDebit = $otherDebitDR - $otherDebitCR;

                // Total expenditure for this month = Debit + Other Debit
                $monthExpenditure = $netDebit + $netOtherDebit;
                $cumulativeExpenditure += $monthExpenditure;
            }

            $balance = $allocation - $cumulativeExpenditure;

            $results[] = [
                'trno' => $row['trno'],
                'program' => $row['program'],
                'project' => $row['project'],
                'sub_project' => $row['sub_project'],
                'object' => $row['object'],
                'subject_name' => $row['subject_name'],
                'allocation' => round($allocation, 2),
                'expenditure' => round($cumulativeExpenditure, 2),
                'balance' => round($balance, 2),
            ];

            $grandTotalAllocation += $allocation;
            $grandTotalExpenditure += $cumulativeExpenditure;
            $grandTotalBalance += $balance;
        }

        // Add grand total row
        $results[] = [
            'trno' => null,
            'program' => null,
            'project' => null,
            'sub_project' => null,
            'object' => null,
            'subject_name' => 'Total',
            'allocation' => round($grandTotalAllocation, 2),
            'expenditure' => round($grandTotalExpenditure, 2),
            'balance' => round($grandTotalBalance, 2),
        ];

        return $results;
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
     * Get filter options (years and months)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from created_at timestamp
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
            \Log::error('Error in Upkeep getFilterOptions: ' . $e->getMessage());
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
            $viewType = $request->input('view_type', 'cumulative');

            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
                ], 422);
            }

            if ($viewType === 'cumulative') {
                $monthsToInclude = range(1, (int)$month);
            } else {
                $monthsToInclude = [(int)$month];
            }

            $upkeepRows = [
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1305],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1305]
            ];

            $upkeepRows = $this->addSubjectNames($upkeepRows);

            $exportData = [];
            $grandTotalAllocation = 0;
            $grandTotalExpenditure = 0;
            $grandTotalBalance = 0;

            foreach ($upkeepRows as $row) {
                $allocation = Budget::where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('subproj', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->sum('amount');

                $cumulativeExpenditure = 0;

                foreach ($monthsToInclude as $currentMonth) {
                    $debitDR = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', $row['trno'])
                        ->where('head', $row['trno'])
                        ->where('program', $row['program'])
                        ->where('project', $row['project'])
                        ->where('sub_project', $row['sub_project'])
                        ->where('object', $row['object'])
                        ->where('dr_cr_code', 1000)
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    $debitCR = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', $row['trno'])
                        ->where('head', $row['trno'])
                        ->where('program', $row['program'])
                        ->where('project', $row['project'])
                        ->where('sub_project', $row['sub_project'])
                        ->where('object', $row['object'])
                        ->where('dr_cr_code', 2000)
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $netDebit = $debitDR - $debitCR;

                    $otherDebitDR = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', '!=', $row['trno'])
                        ->where('head', $row['trno'])
                        ->where('program', $row['program'])
                        ->where('project', $row['project'])
                        ->where('sub_project', $row['sub_project'])
                        ->where('object', $row['object'])
                        ->where('dr_cr_code', 1000)
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    $otherDebitCR = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', '!=', $row['trno'])
                        ->where('head', $row['trno'])
                        ->where('program', $row['program'])
                        ->where('project', $row['project'])
                        ->where('sub_project', $row['sub_project'])
                        ->where('object', $row['object'])
                        ->where('dr_cr_code', 2000)
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $netOtherDebit = $otherDebitDR - $otherDebitCR;
                    $cumulativeExpenditure += ($netDebit + $netOtherDebit);
                }

                $balance = $allocation - $cumulativeExpenditure;

                $exportData[] = [
                    'TR No' => $row['trno'],
                    'Program' => $row['program'],
                    'Project' => $row['project'],
                    'Sub Project' => $row['sub_project'],
                    'Object' => $row['object'],
                    'Subject Name' => $row['subject_name'],
                    'Allocation' => round($allocation, 2),
                    'Expenditure' => round($cumulativeExpenditure, 2),
                    'Balance' => round($balance, 2),
                ];

                $grandTotalAllocation += $allocation;
                $grandTotalExpenditure += $cumulativeExpenditure;
                $grandTotalBalance += $balance;
            }

            // Add grand total row
            $exportData[] = [
                'TR No' => 'TOTAL',
                'Program' => '',
                'Project' => '',
                'Sub Project' => '',
                'Object' => '',
                'Subject Name' => '',
                'Allocation' => round($grandTotalAllocation, 2),
                'Expenditure' => round($grandTotalExpenditure, 2),
                'Balance' => round($grandTotalBalance, 2),
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