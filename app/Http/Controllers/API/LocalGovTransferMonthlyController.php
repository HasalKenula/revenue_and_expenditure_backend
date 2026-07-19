<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocalGovTransferMonthlyController extends Controller
{
    /**
     * Get Local Gov Transfer Monthly Report data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            
            \Log::info('LocalGovTransferMonthly getData called', [
                'year' => $year
            ]);

            // Validate year
            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            // Process the data
            $results = $this->processData($year);

            return response()->json([
                'success' => true,
                'data' => [
                    'local_gov_transfer' => $results,
                    'filters' => [
                        'year' => $year,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in LocalGovTransferMonthly getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Process data for Local Gov Transfer Monthly
     */
    private function processData($year)
    {
        $results = [];
        $monthNames = $this->getMonthNames();
        
        // Define the two categories
        $categories = [
            'Salary Reimbursement' => [
                'trno' => 306,
                'program' => 3,
                'project' => 2,
                'sub_project' => 0,
                'object' => 1503
            ],
            'Members Allowances' => [
                'trno' => 306,
                'program' => 52,
                'project' => 2,
                'sub_project' => 0,
                'object' => 1503
            ]
        ];

        // Loop through all 12 months
        for ($month = 1; $month <= 12; $month++) {
            $monthName = $monthNames[$month];
            $salaryReimbursement = 0;
            $membersAllowances = 0;

            // Calculate for each category
            foreach ($categories as $category => $params) {
                // ========== DEBIT (SAME DEPARTMENT) ==========
                $debitTotal = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $params['trno'])
                    ->where('head', $params['trno'])
                    ->where('program', str_pad($params['program'], 2, '0', STR_PAD_LEFT))
                    ->where('project', str_pad($params['project'], 2, '0', STR_PAD_LEFT))
                    ->where('sub_project', $params['sub_project'])
                    ->where('object', $params['object'])
                    ->where('dr_cr_code', '1000')
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // ========== OTHER DEPARTMENT DEBIT ==========
                $otherDeptTotal = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', '!=', $params['trno'])
                    ->where('head', $params['trno'])
                    ->where('program', str_pad($params['program'], 2, '0', STR_PAD_LEFT))
                    ->where('project', str_pad($params['project'], 2, '0', STR_PAD_LEFT))
                    ->where('sub_project', $params['sub_project'])
                    ->where('object', $params['object'])
                    ->where('dr_cr_code', '1000')
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // ========== SURCHARGE (SAME DEPARTMENT) ==========
                $surchargeTotal = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $params['trno'])
                    ->where('head', $params['trno'])
                    ->where('program', str_pad($params['program'], 2, '0', STR_PAD_LEFT))
                    ->where('project', str_pad($params['project'], 2, '0', STR_PAD_LEFT))
                    ->where('sub_project', $params['sub_project'])
                    ->where('object', $params['object'])
                    ->where('dr_cr_code', '2000')
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // ========== OTHER DEPARTMENT SURCHARGE ==========
                $otherDeptSurchargeTotal = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', '!=', $params['trno'])
                    ->where('head', $params['trno'])
                    ->where('program', str_pad($params['program'], 2, '0', STR_PAD_LEFT))
                    ->where('project', str_pad($params['project'], 2, '0', STR_PAD_LEFT))
                    ->where('sub_project', $params['sub_project'])
                    ->where('object', $params['object'])
                    ->where('dr_cr_code', '2000')
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Calculate expenditure for this month
                $monthExpenditure = ($debitTotal + $otherDeptTotal) - ($surchargeTotal + $otherDeptSurchargeTotal);
                
                // Assign to appropriate category
                if ($category === 'Salary Reimbursement') {
                    $salaryReimbursement = round($monthExpenditure, 2);
                } else if ($category === 'Members Allowances') {
                    $membersAllowances = round($monthExpenditure, 2);
                }
            }

            // Calculate total
            $total = $salaryReimbursement + $membersAllowances;

            // Build result row
            $resultRow = [
                'month' => $monthName,
                'month_number' => $month,
                'salary_reimbursement' => $salaryReimbursement,
                'members_allowances' => $membersAllowances,
                'total' => $total
            ];

            $results[] = $resultRow;
        }

        // Add Total row
        $totalRow = [
            'month' => 'TOTAL',
            'month_number' => null,
            'salary_reimbursement' => array_sum(array_column($results, 'salary_reimbursement')),
            'members_allowances' => array_sum(array_column($results, 'members_allowances')),
            'total' => array_sum(array_column($results, 'total'))
        ];

        $results[] = $totalRow;

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
     * Get filter options (years)
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

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in LocalGovTransferMonthly getFilterOptions: ' . $e->getMessage());
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

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            $results = $this->processData($year);

            $exportData = [];
            foreach ($results as $result) {
                $rowData = [
                    'Month' => $result['month'],
                    'Salary Reimbursement' => $result['salary_reimbursement'],
                    'Members Allowances' => $result['members_allowances'],
                    'Total' => $result['total']
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