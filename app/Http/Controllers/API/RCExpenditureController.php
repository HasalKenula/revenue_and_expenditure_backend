<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RCExpenditureController extends Controller
{
    /**
     * Get Recurrent and Capital Expenditure data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $viewType = $request->input('view_type', 'cumulative'); // 'cumulative' or 'monthly'
            
            \Log::info('RCExpenditure getData called', [
                'year' => $year,
                'month' => $month,
                'view_type' => $viewType
            ]);

            // Validate year
            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            // Determine which months to show
            if ($month && $month > 0) {
                if ($viewType === 'cumulative') {
                    // Show Jan to selected month
                    $monthsToShow = range(1, (int)$month);
                } else {
                    // Show only the selected month
                    $monthsToShow = [(int)$month];
                }
            } else {
                // If no month selected, show all months
                $monthsToShow = range(1, 12);
            }

            // Define object ranges
            $objectRanges = [
                'personal_emolument' => ['label' => 'Personal Emolument', 'min' => 1001, 'max' => 1100],
                'travelling_expenses' => ['label' => 'Travelling Expenses', 'min' => 1101, 'max' => 1200],
                'supplies' => ['label' => 'Supplies', 'min' => 1201, 'max' => 1300],
                'maintenance_expenditure' => ['label' => 'Maintenance Expenditure', 'min' => 1301, 'max' => 1400],
                'contractual_services' => ['label' => 'Contractual Services', 'min' => 1401, 'max' => 1500],
                'transfers_grants' => ['label' => 'Transfers and Grants', 'min' => 1501, 'max' => 1600],
                'interest_payment' => ['label' => 'Interest Payment', 'min' => 1601, 'max' => 1700],
                'other_recurrent' => ['label' => 'Other Recurrent Expenditure', 'min' => 1701, 'max' => 1800],
                'rehabilitation_capital' => ['label' => 'Rehabilitation and Improvement of Capital Assets', 'min' => 2001, 'max' => 2100],
                'acquisition_capital' => ['label' => 'Acquisition of Capital Assets', 'min' => 2101, 'max' => 2200],
                'capital_transfers' => ['label' => 'Capital Transfers', 'min' => 2201, 'max' => 2300],
                'human_resource' => ['label' => 'Human Resource', 'min' => 2401, 'max' => 2500],
                'other_capital' => ['label' => 'Other Capital Expenditure', 'min' => 2501, 'max' => 2600],
            ];

            // Get heads from 300 to 325
            $heads = MonthlyFincance::whereYear('created_at', $year)
                ->whereNotNull('head')
                ->whereBetween('head', [300, 325])
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
            $grandTotals = [];

            foreach ($objectRanges as $key => $range) {
                $grandTotals[$key] = array_fill_keys($monthsToShow, 0);
            }

            foreach ($heads as $head) {
                $row = [
                    'head' => $head,
                ];
                
                $cumulativeTotals = [];

                foreach ($objectRanges as $key => $range) {
                    $monthlyTotals = [];
                    $cumulativeTotal = 0;

                    foreach ($monthsToShow as $monthNum) {
                        // Get all objects in this range for this head and month
                        $objects = MonthlyFincance::whereYear('created_at', $year)
                            ->where('month', $monthNum)
                            ->where('head', $head)
                            ->whereBetween('object', [$range['min'], $range['max']])
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

                            // Net amount = DR - CR
                            $netAmount = $drTotal - $crTotal;
                            $totalAmount += $netAmount;
                        }

                        // For cumulative view, add to running total
                        if ($viewType === 'cumulative') {
                            $cumulativeTotal += $totalAmount;
                            $monthlyTotals[$monthNum] = round($cumulativeTotal, 2);
                        } else {
                            // Monthly view - show only the month's amount
                            $monthlyTotals[$monthNum] = round($totalAmount, 2);
                        }
                        
                        $grandTotals[$key][$monthNum] += $totalAmount;
                    }

                    // Store monthly totals for this category
                    $row[$key] = $monthlyTotals;
                    
                    // For cumulative view, total is the cumulative total
                    if ($viewType === 'cumulative') {
                        $cumulativeTotals[$key] = !empty($monthlyTotals) ? round(end($monthlyTotals), 2) : 0;
                    } else {
                        // For monthly view, total is the sum of all months shown
                        $cumulativeTotals[$key] = round(array_sum($monthlyTotals), 2);
                    }
                }

                // Add cumulative total for each category
                foreach ($objectRanges as $key => $range) {
                    $row["{$key}_total"] = $cumulativeTotals[$key];
                }

                $results[] = $row;
            }

            // Add grand total row
            $grandTotalRow = [
                'head' => null,
                'head_name' => 'Total',
            ];

            foreach ($objectRanges as $key => $range) {
                $grandTotalRow[$key] = [];
                $grandCumulative = 0;
                foreach ($monthsToShow as $monthNum) {
                    if ($viewType === 'cumulative') {
                        $grandCumulative += $grandTotals[$key][$monthNum];
                        $grandTotalRow[$key][$monthNum] = round($grandCumulative, 2);
                    } else {
                        $grandTotalRow[$key][$monthNum] = round($grandTotals[$key][$monthNum], 2);
                    }
                }
                if ($viewType === 'cumulative') {
                    $grandTotalRow["{$key}_total"] = !empty($grandTotalRow[$key]) ? round(end($grandTotalRow[$key]), 2) : 0;
                } else {
                    $grandTotalRow["{$key}_total"] = round(array_sum($grandTotals[$key]), 2);
                }
            }

            $results[] = $grandTotalRow;

            // Get month names for display
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
                    'view_type' => $viewType,
                    'object_ranges' => $objectRanges,
                    'head_range' => '300-325',
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in RCExpenditure getData: ' . $e->getMessage());
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
            \Log::error('Error in RCExpenditure getFilterOptions: ' . $e->getMessage());
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
            $viewType = $request->input('view_type', 'cumulative');

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            if ($month && $month > 0) {
                if ($viewType === 'cumulative') {
                    $monthsToShow = range(1, (int)$month);
                } else {
                    $monthsToShow = [(int)$month];
                }
            } else {
                $monthsToShow = range(1, 12);
            }

            $objectRanges = [
                'personal_emolument' => ['label' => 'Personal Emolument', 'min' => 1001, 'max' => 1100],
                'travelling_expenses' => ['label' => 'Travelling Expenses', 'min' => 1101, 'max' => 1200],
                'supplies' => ['label' => 'Supplies', 'min' => 1201, 'max' => 1300],
                'maintenance_expenditure' => ['label' => 'Maintenance Expenditure', 'min' => 1301, 'max' => 1400],
                'contractual_services' => ['label' => 'Contractual Services', 'min' => 1401, 'max' => 1500],
                'transfers_grants' => ['label' => 'Transfers and Grants', 'min' => 1501, 'max' => 1600],
                'interest_payment' => ['label' => 'Interest Payment', 'min' => 1601, 'max' => 1700],
                'other_recurrent' => ['label' => 'Other Recurrent Expenditure', 'min' => 1701, 'max' => 1800],
                'rehabilitation_capital' => ['label' => 'Rehabilitation and Improvement of Capital Assets', 'min' => 2001, 'max' => 2100],
                'acquisition_capital' => ['label' => 'Acquisition of Capital Assets', 'min' => 2101, 'max' => 2200],
                'capital_transfers' => ['label' => 'Capital Transfers', 'min' => 2201, 'max' => 2300],
                'human_resource' => ['label' => 'Human Resource', 'min' => 2401, 'max' => 2500],
                'other_capital' => ['label' => 'Other Capital Expenditure', 'min' => 2501, 'max' => 2600],
            ];

            $heads = MonthlyFincance::whereYear('created_at', $year)
                ->whereNotNull('head')
                ->whereBetween('head', [300, 325])
                ->distinct()
                ->orderBy('head')
                ->pluck('head')
                ->values();

            $monthNames = $this->getMonthNames();
            $exportData = [];
            $grandTotals = [];

            foreach ($objectRanges as $key => $range) {
                $grandTotals[$key] = array_fill_keys($monthsToShow, 0);
            }

            foreach ($heads as $head) {
                $row = ['Head Code' => $head];

                foreach ($objectRanges as $key => $range) {
                    $totalAmount = 0;
                    $cumulativeTotal = 0;
                    
                    foreach ($monthsToShow as $monthNum) {
                        $objects = MonthlyFincance::whereYear('created_at', $year)
                            ->where('month', $monthNum)
                            ->where('head', $head)
                            ->whereBetween('object', [$range['min'], $range['max']])
                            ->whereNotNull('object')
                            ->distinct()
                            ->pluck('object')
                            ->values();

                        $monthTotal = 0;

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
                            $monthTotal += $netAmount;
                        }

                        if ($viewType === 'cumulative') {
                            $cumulativeTotal += $monthTotal;
                            $totalAmount = $cumulativeTotal;
                        } else {
                            $totalAmount += $monthTotal;
                        }
                        
                        $grandTotals[$key][$monthNum] += $monthTotal;
                    }

                    $row[$range['label']] = round($totalAmount, 2);
                }

                $exportData[] = $row;
            }

            // Add grand total row
            $grandTotalRow = ['Head Code' => 'TOTAL'];
            foreach ($objectRanges as $key => $range) {
                $grandTotal = array_sum($grandTotals[$key]);
                $grandTotalRow[$range['label']] = round($grandTotal, 2);
            }
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