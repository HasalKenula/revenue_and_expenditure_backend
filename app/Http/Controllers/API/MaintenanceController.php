<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    /**
     * Get Maintenance data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            
            \Log::info('Maintenance getData called', [
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

            // Determine which months to show (cumulative from January to selected month)
            $monthsToShow = range(1, (int)$month);

            // Define categories with their object ranges (RECA 1501-1600 removed)
            $categories = [
                [
                    'key' => 'te',
                    'name' => 'Travelling Expenses',
                    'object_range' => ['min' => 1101, 'max' => 1200]
                ],
                [
                    'key' => 'su',
                    'name' => 'Supplies',
                    'object_range' => ['min' => 1201, 'max' => 1300]
                ],
                [
                    'key' => 'main',
                    'name' => 'Maintenance Expenditure',
                    'object_range' => ['min' => 1301, 'max' => 1400]
                ],
                [
                    'key' => 'cs',
                    'name' => 'Contractual Services',
                    'object_range' => ['min' => 1401, 'max' => 1500]
                ],
                
                [
                    'key' => 'reca_capital',
                    'name' => 'Rehabilitation & Improvement of Capital Assets',
                    'object_range' => ['min' => 2001, 'max' => 2100]
                ],
                [
                    'key' => 'acca',
                    'name' => 'Acquisition of Capital Assets',
                    'object_range' => ['min' => 2101, 'max' => 2200]
                ],
                [
                    'key' => 'oca',
                    'name' => 'Other Capital Expenditure',
                    'object_range' => ['min' => 2501, 'max' => 2600]
                ]
            ];

            $results = [];
            $grandTotalAllocation = 0;
            $grandTotalExpenditure = 0;
            $grandTotalBalance = 0;

            foreach ($categories as $category) {
                // Get Allocation from Budget table
                $allocation = Budget::whereYear('created_at', $year)
                    ->whereBetween('object', [$category['object_range']['min'], $category['object_range']['max']])
                    ->sum('amount');

                // Get Expenditure (cumulative from Jan to selected month)
                $expenditure = 0;
                foreach ($monthsToShow as $currentMonth) {
                    // Get DR amounts (code 1000, DR='DR') - Add
                    $drTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->whereBetween('object', [$category['object_range']['min'], $category['object_range']['max']])
                        ->where('dr_cr_code', 1000)
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    // Get CR amounts (code 2000, DR='CR') - Subtract
                    $crTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->whereBetween('object', [$category['object_range']['min'], $category['object_range']['max']])
                        ->where('dr_cr_code', 2000)
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    // Net expenditure for this month = DR - CR
                    $expenditure += ($drTotal - $crTotal);
                }

                $balance = $allocation - $expenditure;

                $results[] = [
                    'category' => $category['key'],
                    'name' => $category['name'],
                    'allocation' => round($allocation, 2),
                    'expenditure' => round($expenditure, 2),
                    'balance' => round($balance, 2),
                    'object_range' => $category['object_range']
                ];

                $grandTotalAllocation += $allocation;
                $grandTotalExpenditure += $expenditure;
                $grandTotalBalance += $balance;
            }

            // Add grand total row
            $results[] = [
                'category' => 'grand_total',
                'name' => 'Grand Total',
                'allocation' => round($grandTotalAllocation, 2),
                'expenditure' => round($grandTotalExpenditure, 2),
                'balance' => round($grandTotalBalance, 2),
                'object_range' => null
            ];

            // Get month names for display
            $monthNames = $this->getMonthNames();
            $monthNamesToShow = [];
            foreach ($monthsToShow as $monthNum) {
                $monthNamesToShow[$monthNum] = $monthNames[$monthNum];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'months' => $monthsToShow,
                    'month_names' => $monthNamesToShow,
                    'year' => $year,
                    'selected_month' => $month,
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in Maintenance getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
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
     * Get filter options (years and months)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from budgets table
            $years = Budget::select(DB::raw('YEAR(created_at) as year'))
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            // If no years found, provide default range
            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear));
            }

            // Months 1-12
            $months = collect(range(1, 12));

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                    'months' => $months,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in Maintenance getFilterOptions: ' . $e->getMessage());
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

            // Get data using the same logic
            $monthsToShow = range(1, (int)$month);

            // Categories without RECA (1501-1600)
            $categories = [
                ['key' => 'te', 'name' => 'Travelling Expenses', 'min' => 1101, 'max' => 1200],
                ['key' => 'su', 'name' => 'Supplies', 'min' => 1201, 'max' => 1300],
                ['key' => 'main', 'name' => 'Maintenance Expenditure', 'min' => 1301, 'max' => 1400],
                ['key' => 'cs', 'name' => 'Contractual Services', 'min' => 1401, 'max' => 1500],
                ['key' => 'reca_capital', 'name' => 'Rehabilitation & Improvement of Capital Assets', 'min' => 2001, 'max' => 2100],
                ['key' => 'acca', 'name' => 'Acquisition of Capital Assets', 'min' => 2101, 'max' => 2200],
                ['key' => 'oca', 'name' => 'Other Capital Expenditure', 'min' => 2501, 'max' => 2600]
            ];

            $exportData = [];
            $grandTotalAllocation = 0;
            $grandTotalExpenditure = 0;
            $grandTotalBalance = 0;

            foreach ($categories as $category) {
                $allocation = Budget::whereYear('created_at', $year)
                    ->whereBetween('object', [$category['min'], $category['max']])
                    ->sum('amount');

                $expenditure = 0;
                foreach ($monthsToShow as $currentMonth) {
                    $drTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->whereBetween('object', [$category['min'], $category['max']])
                        ->where('dr_cr_code', 1000)
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    $crTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $currentMonth)
                        ->whereBetween('object', [$category['min'], $category['max']])
                        ->where('dr_cr_code', 2000)
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $expenditure += ($drTotal - $crTotal);
                }

                $balance = $allocation - $expenditure;

                $exportData[] = [
                    'Category' => $category['name'],
                    'Allocation' => round($allocation, 2),
                    'Expenditure' => round($expenditure, 2),
                    'Balance' => round($balance, 2),
                ];

                $grandTotalAllocation += $allocation;
                $grandTotalExpenditure += $expenditure;
                $grandTotalBalance += $balance;
            }

            // Add grand total row
            $exportData[] = [
                'Category' => 'GRAND TOTAL',
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