<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StampDutyMonthlyController extends Controller
{
    /**
     * Get Stamp Duty Monthly Report data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            
            \Log::info('StampDutyMonthly getData called', [
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

            // Determine months to show (Jan to selected month)
            $monthsToShow = range(1, (int)$month);

            // ========== STAMP DUTY ROWS ==========
            $stampDutyRows = [
                [
                    'trno' => 306,
                    'program' => 3,
                    'project' => 2,
                    'sub_project' => 1,
                    'object' => 1503
                ],
                [
                    'trno' => 306,
                    'program' => 3,
                    'project' => 2,
                    'sub_project' => 2,
                    'object' => 1503
                ]
            ];

            // Get subject names from budgets table
            $stampDutyRows = $this->addSubjectNames($stampDutyRows);

            // Process the data
            $results = $this->processRows($stampDutyRows, $year, $monthsToShow);

            // Get month names for display
            $monthNames = $this->getMonthNames();
            $monthNamesToShow = [];
            foreach ($monthsToShow as $monthNum) {
                $monthNamesToShow[$monthNum] = $monthNames[$monthNum];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'stamp_duty_report' => $results,
                    'months' => $monthsToShow,
                    'month_names' => $monthNamesToShow,
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in StampDutyMonthly getData: ' . $e->getMessage());
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
     * Process rows for Stamp Duty report
     */
    private function processRows($rows, $year, $monthsToShow)
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

            $monthlyExpenditures = [];
            $totalExpenditure = 0;

            foreach ($monthsToShow as $currentMonth) {
                // ========== DEBIT (SAME DEPARTMENT) - SELECTED MONTH ONLY ==========
                $debitQuery = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', '1000')
                    ->where('dr_cr', 'DR');

                $debitTotal = $debitQuery->sum('cash_xe');

                // ========== OTHER DEPARTMENT DEBIT - SELECTED MONTH ONLY ==========
                $otherDeptQuery = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', '1000')
                    ->where('dr_cr', 'DR');

                $otherDeptTotal = $otherDeptQuery->sum('cash_xe');

                // ========== SURCHARGE (SAME DEPARTMENT) - SELECTED MONTH ONLY ==========
                $surchargeQuery = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', '2000')
                    ->where('dr_cr', 'CR');

                $surchargeTotal = $surchargeQuery->sum('cash_xe');

                // ========== OTHER DEPARTMENT SURCHARGE - SELECTED MONTH ONLY ==========
                $otherDeptSurchargeQuery = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', '2000')
                    ->where('dr_cr', 'CR');

                $otherDeptSurchargeTotal = $otherDeptSurchargeQuery->sum('cash_xe');

                // Calculate net expenditure for this month
                // (Debit + Other Dept Debit) - (Surcharge + Other Dept Surcharge)
                $monthExpenditure = ($debitTotal + $otherDeptTotal) - ($surchargeTotal + $otherDeptSurchargeTotal);
                
                $monthlyExpenditures[$currentMonth] = round($monthExpenditure, 2);
                $totalExpenditure += $monthExpenditure;
            }

            $balance = $allocation - $totalExpenditure;

            // Build the result row with monthly columns
            $resultRow = [
                'trno' => $row['trno'],
                'program' => $row['program'],
                'project' => $row['project'],
                'sub_project' => $row['sub_project'],
                'object' => $row['object'],
                'subject_name' => $row['subject_name'],
                'allocation' => round($allocation, 2),
                'total_expenditure' => round($totalExpenditure, 2),
                'balance' => round($balance, 2),
            ];

            // Add monthly expenditure columns
            foreach ($monthsToShow as $monthNum) {
                $resultRow["month_{$monthNum}"] = $monthlyExpenditures[$monthNum] ?? 0;
            }

            $results[] = $resultRow;

            $grandTotalAllocation += $allocation;
            $grandTotalExpenditure += $totalExpenditure;
            $grandTotalBalance += $balance;
        }

        // Add grand total row
        $grandTotalRow = [
            'trno' => null,
            'program' => null,
            'project' => null,
            'sub_project' => null,
            'object' => null,
            'subject_name' => 'Total',
            'allocation' => round($grandTotalAllocation, 2),
            'total_expenditure' => round($grandTotalExpenditure, 2),
            'balance' => round($grandTotalBalance, 2),
        ];

        // Add monthly totals for grand total row
        foreach ($monthsToShow as $monthNum) {
            $monthTotal = 0;
            foreach ($results as $result) {
                if (isset($result["month_{$monthNum}"])) {
                    $monthTotal += $result["month_{$monthNum}"];
                }
            }
            $grandTotalRow["month_{$monthNum}"] = round($monthTotal, 2);
        }

        $results[] = $grandTotalRow;

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
            \Log::error('Error in StampDutyMonthly getFilterOptions: ' . $e->getMessage());
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

            $monthsToShow = range(1, (int)$month);

            $stampDutyRows = [
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 1503],
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 1503]
            ];

            $stampDutyRows = $this->addSubjectNames($stampDutyRows);

            $exportData = [];
            $grandTotalAllocation = 0;
            $grandTotalExpenditure = 0;
            $grandTotalBalance = 0;
            $monthlyGrandTotals = array_fill_keys($monthsToShow, 0);

            foreach ($stampDutyRows as $row) {
                $allocation = Budget::where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('subproj', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->sum('amount');

                $monthlyExpenditures = [];
                $totalExpenditure = 0;

                foreach ($monthsToShow as $currentMonth) {
                    $debitTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', $row['trno'])
                        ->where('head', $row['trno'])
                        ->where('program', $row['program'])
                        ->where('project', $row['project'])
                        ->where('sub_project', $row['sub_project'])
                        ->where('object', $row['object'])
                        ->where('dr_cr_code', '1000')
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    $otherDeptTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', '!=', $row['trno'])
                        ->where('head', $row['trno'])
                        ->where('program', $row['program'])
                        ->where('project', $row['project'])
                        ->where('sub_project', $row['sub_project'])
                        ->where('object', $row['object'])
                        ->where('dr_cr_code', '1000')
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    $surchargeTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', $row['trno'])
                        ->where('head', $row['trno'])
                        ->where('program', $row['program'])
                        ->where('project', $row['project'])
                        ->where('sub_project', $row['sub_project'])
                        ->where('object', $row['object'])
                        ->where('dr_cr_code', '2000')
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $otherDeptSurchargeTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->where('trno', '!=', $row['trno'])
                        ->where('head', $row['trno'])
                        ->where('program', $row['program'])
                        ->where('project', $row['project'])
                        ->where('sub_project', $row['sub_project'])
                        ->where('object', $row['object'])
                        ->where('dr_cr_code', '2000')
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $monthExpenditure = ($debitTotal + $otherDeptTotal) - ($surchargeTotal + $otherDeptSurchargeTotal);
                    $monthlyExpenditures[$currentMonth] = round($monthExpenditure, 2);
                    $totalExpenditure += $monthExpenditure;
                    $monthlyGrandTotals[$currentMonth] += $monthExpenditure;
                }

                $balance = $allocation - $totalExpenditure;

                $rowData = [
                    'TR No' => $row['trno'],
                    'Program' => $row['program'],
                    'Project' => $row['project'],
                    'Sub Project' => $row['sub_project'],
                    'Object' => $row['object'],
                    'Subject Name' => $row['subject_name'],
                    'Allocation' => round($allocation, 2),
                ];

                foreach ($monthsToShow as $monthNum) {
                    $monthName = $this->getMonthNames()[$monthNum];
                    $rowData[$monthName] = $monthlyExpenditures[$monthNum] ?? 0;
                }

                $rowData['Total Expenditure'] = round($totalExpenditure, 2);
                $rowData['Balance'] = round($balance, 2);

                $exportData[] = $rowData;

                $grandTotalAllocation += $allocation;
                $grandTotalExpenditure += $totalExpenditure;
                $grandTotalBalance += $balance;
            }

            // Add grand total row
            $grandTotalRow = [
                'TR No' => 'TOTAL',
                'Program' => '',
                'Project' => '',
                'Sub Project' => '',
                'Object' => '',
                'Subject Name' => '',
                'Allocation' => round($grandTotalAllocation, 2),
            ];

            foreach ($monthsToShow as $monthNum) {
                $monthName = $this->getMonthNames()[$monthNum];
                $grandTotalRow[$monthName] = round($monthlyGrandTotals[$monthNum], 2);
            }

            $grandTotalRow['Total Expenditure'] = round($grandTotalExpenditure, 2);
            $grandTotalRow['Balance'] = round($grandTotalBalance, 2);

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