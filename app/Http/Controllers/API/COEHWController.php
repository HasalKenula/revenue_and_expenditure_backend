<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class COEHWController extends Controller
{
    /**
     * Get Classification of Expenditure Head Wise data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month'); // Selected month filter
            
            \Log::info('COEHW getData called', [
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

            // Get heads from 300 to 325 only
            $heads = MonthlyFincance::whereYear('created_at', $year)
                ->whereNotNull('head')
                ->whereBetween('head', [300, 325]) // Filter heads 300-325
                ->distinct()
                ->orderBy('head')
                ->pluck('head')
                ->values();

            if ($heads->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No records found for heads 300-325 in the selected year'
                ]);
            }

            $results = [];
            $grandTotals = array_fill_keys($monthsToShow, 0);

            foreach ($heads as $head) {
                $row = [
                    'head' => $head,
                ];
                
                $monthlyTotals = [];
                $cumulativeTotal = 0; // Track cumulative total for this head

                foreach ($monthsToShow as $monthNum) {
                    // Get all objects for this head and month
                    $objects = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $monthNum)
                        ->where('head', $head)
                        ->whereNotNull('object')
                        ->distinct()
                        ->pluck('object')
                        ->values();

                    $totalAmount = 0;

                    foreach ($objects as $object) {
                        // Get DR amount (code 1000) - Add
                        $drTotal = MonthlyFincance::whereYear('created_at', $year)
                            ->where('month', $monthNum)
                            ->where('head', $head)
                            ->where('object', $object)
                            ->where('dr_cr_code', 1000)
                            ->sum('cash_xe');

                        // Get CR amount (code 2000) - Subtract
                        $crTotal = MonthlyFincance::whereYear('created_at', $year)
                            ->where('month', $monthNum)
                            ->where('head', $head)
                            ->where('object', $object)
                            ->where('dr_cr_code', 2000)
                            ->sum('cash_xe');

                        // Net amount for this object = DR - CR
                        $netAmount = $drTotal - $crTotal;
                        $totalAmount += $netAmount;
                    }

                    $monthlyTotals[$monthNum] = round($totalAmount, 2);
                    
                    // Add to cumulative total
                    $cumulativeTotal += $totalAmount;
                    
                    // Add to grand total
                    $grandTotals[$monthNum] += $totalAmount;
                }

                // Add monthly totals to row
                foreach ($monthsToShow as $monthNum) {
                    $row["month_{$monthNum}"] = $monthlyTotals[$monthNum];
                }

                // Calculate cumulative total for this head up to selected month
                $row['total'] = round($cumulativeTotal, 2);
                
                $results[] = $row;
            }

            // Add grand total row
            $grandTotalRow = [
                'head' => null,
                'head_name' => 'Total',
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
                    'head_range' => '300-325',
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in COEHW getData: ' . $e->getMessage());
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
            \Log::error('Error in COEHW getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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

            // Get data using the same logic - heads 300-325 only
            $heads = MonthlyFincance::whereYear('created_at', $year)
                ->whereNotNull('head')
                ->whereBetween('head', [300, 325]) // Filter heads 300-325
                ->distinct()
                ->orderBy('head')
                ->pluck('head')
                ->values();

            $monthNames = $this->getMonthNames();
            $exportData = [];
            $grandTotals = array_fill_keys($monthsToShow, 0);

            foreach ($heads as $head) {
                $row = [
                    'Head Code' => $head,
                ];
                
                foreach ($monthsToShow as $monthNum) {
                    $objects = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $monthNum)
                        ->where('head', $head)
                        ->whereNotNull('object')
                        ->distinct()
                        ->pluck('object')
                        ->values();

                    $totalAmount = 0;

                    foreach ($objects as $object) {
                        $drTotal = MonthlyFincance::whereYear('created_at', $year)
                            ->where('month', $monthNum)
                            ->where('head', $head)
                            ->where('object', $object)
                            ->where('dr_cr_code', 1000)
                            ->sum('cash_xe');

                        $crTotal = MonthlyFincance::whereYear('created_at', $year)
                            ->where('month', $monthNum)
                            ->where('head', $head)
                            ->where('object', $object)
                            ->where('dr_cr_code', 2000)
                            ->sum('cash_xe');

                        $netAmount = $drTotal - $crTotal;
                        $totalAmount += $netAmount;
                    }

                    $row[$monthNames[$monthNum]] = round($totalAmount, 2);
                    $grandTotals[$monthNum] += $totalAmount;
                }

                // Calculate cumulative total
                $cumulativeTotal = array_sum(array_slice($row, 1));
                $row['Total'] = round($cumulativeTotal, 2);
                $exportData[] = $row;
            }

            // Add grand total row
            $grandTotalRow = ['Head Code' => 'TOTAL'];
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