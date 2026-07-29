<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StampDutySummaryController extends Controller
{
    /**
     * Get Stamp Duty Summary Report data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            
            \Log::info('StampDutySummary getData called', [
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

            // Define the two rows
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

            // Process the data
            $results = $this->processRows($stampDutyRows, $year, $month);

            return response()->json([
                'success' => true,
                'data' => [
                    'stamp_duty_summary' => $results,
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in StampDutySummary getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Process rows for Stamp Duty Summary
     */
    private function processRows($rows, $year, $selectedMonth)
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

            // Get subject name from Budget
            $budget = Budget::where('head', $row['trno'])
                ->where('program', $row['program'])
                ->where('project', $row['project'])
                ->where('subproj', $row['sub_project'])
                ->where('object', $row['object'])
                ->first();

            $subjectName = $budget ? $budget->objname : 'Unknown';

            // Calculate total expenditure (cumulative from January to selected month)
            $totalExpenditure = 0;

            // Loop from January to selected month (cumulative)
            for ($currentMonth = 1; $currentMonth <= $selectedMonth; $currentMonth++) {
                // ========== DEBIT (SAME DEPARTMENT) ==========
                $debitTotal = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', str_pad($row['program'], 2, '0', STR_PAD_LEFT))
                    ->where('project', str_pad($row['project'], 2, '0', STR_PAD_LEFT))
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', '1000')
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // ========== OTHER DEPARTMENT DEBIT ==========
                $otherDeptTotal = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', str_pad($row['program'], 2, '0', STR_PAD_LEFT))
                    ->where('project', str_pad($row['project'], 2, '0', STR_PAD_LEFT))
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', '1000')
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // ========== SURCHARGE (SAME DEPARTMENT) ==========
                $surchargeTotal = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', str_pad($row['program'], 2, '0', STR_PAD_LEFT))
                    ->where('project', str_pad($row['project'], 2, '0', STR_PAD_LEFT))
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', '2000')
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // ========== OTHER DEPARTMENT SURCHARGE ==========
                $otherDeptSurchargeTotal = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', str_pad($row['program'], 2, '0', STR_PAD_LEFT))
                    ->where('project', str_pad($row['project'], 2, '0', STR_PAD_LEFT))
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', '2000')
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Calculate expenditure for this month
                $monthExpenditure = ($debitTotal + $otherDeptTotal) - ($surchargeTotal + $otherDeptSurchargeTotal);
                
                // Add to cumulative total
                $totalExpenditure += $monthExpenditure;
            }

            $balance = $allocation - $totalExpenditure;

            // Build the result row with only required columns
            $resultRow = [
                'trno' => $row['trno'],
                'program' => $row['program'],
                'project' => $row['project'],
                'sub_project' => $row['sub_project'],
                'object' => $row['object'],
                'subject_name' => $subjectName,
                'allocation' => round($allocation, 2),
                'total_expenditure' => round($totalExpenditure, 2),
                'balance' => round($balance, 2),
            ];

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
            'subject_name' => 'TOTAL',
            'allocation' => round($grandTotalAllocation, 2),
            'total_expenditure' => round($grandTotalExpenditure, 2),
            'balance' => round($grandTotalBalance, 2),
        ];

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
            \Log::error('Error in StampDutySummary getFilterOptions: ' . $e->getMessage());
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

            $stampDutyRows = [
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 1503],
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 1503]
            ];

            $results = $this->processRows($stampDutyRows, $year, $month);

            $exportData = [];
            foreach ($results as $result) {
                $rowData = [
                    'TR No' => $result['trno'] ?? 'TOTAL',
                    'Program' => $result['program'] ?? '',
                    'Project' => $result['project'] ?? '',
                    'Sub Project' => $result['sub_project'] ?? '',
                    'Object' => $result['object'] ?? '',
                    'Subject Name' => $result['subject_name'],
                    'Allocation' => $result['allocation'],
                    'Total Expenditure' => $result['total_expenditure'],
                    'Balance' => $result['balance'],
                ];

                $exportData[] = $rowData;
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