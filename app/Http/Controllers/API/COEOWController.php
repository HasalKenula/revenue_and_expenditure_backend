<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class COEOWController extends Controller
{
    /**
     * Get Classification of Expenditure Object Wise data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month'); // Selected month filter
            
            \Log::info('COEOW getData called', [
                'year' => $year,
                'month' => $month
            ]);

            // Validate year
            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            // Determine which months to show (Jan to selected month)
            if ($month && $month > 0) {
                $monthsToShow = range(1, (int)$month);
            } else {
                // If no month selected, show all months
                $monthsToShow = range(1, 12);
            }

            // Get all objects (1000-3000 range)
            $objects = MonthlyFincance::whereYear('created_at', $year)
                ->whereBetween('object', [1000, 3000])
                ->whereNotNull('object')
                ->distinct()
                ->orderBy('object')
                ->pluck('object')
                ->values();

            if ($objects->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No records found for the selected year'
                ]);
            }

            $results = [];
            $grandTotals = array_fill_keys($monthsToShow, 0);

            foreach ($objects as $object) {
                $row = [
                    'object' => $object,
                    'object_name' => $this->getObjectName($object),
                ];
                
                $monthlyTotals = [];
                $cumulativeTotal = 0; // Track cumulative total for this object

                foreach ($monthsToShow as $monthNum) {
                    // Calculate total for this object and month
                    $drTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $monthNum)
                        ->where('object', $object)
                        ->where('dr_cr_code', 1000) // DR - Add
                        ->sum('cash_xe');

                    $crTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $monthNum)
                        ->where('object', $object)
                        ->where('dr_cr_code', 2000) // CR - Subtract
                        ->sum('cash_xe');

                    // Net amount = DR - CR
                    $netAmount = $drTotal - $crTotal;
                    $monthlyTotals[$monthNum] = round($netAmount, 2);
                    
                    // Add to cumulative total
                    $cumulativeTotal += $netAmount;
                    
                    // Add to grand total
                    $grandTotals[$monthNum] += $netAmount;
                }

                // Add monthly totals to row
                foreach ($monthsToShow as $monthNum) {
                    $row["month_{$monthNum}"] = $monthlyTotals[$monthNum];
                }

                // Calculate cumulative total for this object up to selected month
                $row['total'] = round($cumulativeTotal, 2);
                
                $results[] = $row;
            }

            // Add grand total row
            $grandTotalRow = [
                'object' => null,
                'object_name' => 'Total',
            ];
            $grandCumulativeTotal = 0;
            foreach ($monthsToShow as $monthNum) {
                $grandTotalRow["month_{$monthNum}"] = round($grandTotals[$monthNum], 2);
                $grandCumulativeTotal += $grandTotals[$monthNum];
            }
            $grandTotalRow['total'] = round($grandCumulativeTotal, 2);
            $results[] = $grandTotalRow;

            // Get all month names for display
            $allMonthNames = $this->getMonthNames();
            $monthNamesToShow = [];
            foreach ($monthsToShow as $monthNum) {
                $monthNamesToShow[$monthNum] = $allMonthNames[$monthNum];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'months' => $monthsToShow,
                    'month_names' => $monthNamesToShow,
                    'year' => $year,
                    'selected_month' => $month,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in COEOW getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get filter options (years and months)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from created_at
            $years = MonthlyFincance::select(DB::raw('YEAR(created_at) as year'))
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
            \Log::error('Error in COEOW getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get object name based on object code
     */
    private function getObjectName($object)
    {
        // You can customize this mapping based on your actual object names
        $objectNames = [
            // Add your object names here
            // Example:
            // 1001 => 'Salaries',
            // 1002 => 'Wages',
            // 2001 => 'Office Supplies',
            // 3001 => 'Travel Expenses',
        ];

        return $objectNames[$object] ?? "Object {$object}";
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
     * Export data to CSV
     */
    public function export(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            // Determine which months to show
            if ($month && $month > 0) {
                $monthsToShow = range(1, (int)$month);
            } else {
                $monthsToShow = range(1, 12);
            }

            // Get data using the same logic
            $objects = MonthlyFincance::whereYear('created_at', $year)
                ->whereBetween('object', [1000, 3000])
                ->whereNotNull('object')
                ->distinct()
                ->orderBy('object')
                ->pluck('object')
                ->values();

            $monthNames = $this->getMonthNames();
            $exportData = [];
            $grandTotals = array_fill_keys($monthsToShow, 0);

            foreach ($objects as $object) {
                $row = [
                    'Object Code' => $object,
                ];
                
                foreach ($monthsToShow as $monthNum) {
                    $drTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $monthNum)
                        ->where('object', $object)
                        ->where('dr_cr_code', 1000)
                        ->sum('cash_xe');

                    $crTotal = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $monthNum)
                        ->where('object', $object)
                        ->where('dr_cr_code', 2000)
                        ->sum('cash_xe');

                    $netAmount = $drTotal - $crTotal;
                    $row[$monthNames[$monthNum]] = round($netAmount, 2);
                    $grandTotals[$monthNum] += $netAmount;
                }

                // Calculate cumulative total
                $cumulativeTotal = array_sum(array_slice($row, 1));
                $row['Total'] = round($cumulativeTotal, 2);
                $exportData[] = $row;
            }

            // Add grand total row
            $grandTotalRow = ['Object Code' => 'TOTAL'];
            $grandCumulativeTotal = 0;
            foreach ($monthsToShow as $monthNum) {
                $grandTotalRow[$monthNames[$monthNum]] = round($grandTotals[$monthNum], 2);
                $grandCumulativeTotal += $grandTotals[$monthNum];
            }
            $grandTotalRow['Total'] = round($grandCumulativeTotal, 2);
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