<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Treasury;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActualRevenueReportController extends Controller
{
    /**
     * Get Monthly Revenue Report data
     */
    public function getData(Request $request)
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

            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Define revenue structure with categories and their codes - GROUPED BY NAME
            $revenueStructure = [
                'A' => [
                    'title' => 'Taxes on Local Goods and Services',
                    'items' => [
                        ['codes' => ['1002-04-03', '1002-04-04', '1002-04-05', '1002-04-06'], 'name' => 'Excise Duty On Liquor'],
                        ['codes' => ['1003-01-01', '1003-01-02'], 'name' => 'Motor Vehicle Revenue Licence Fees'],
                        ['codes' => ['1002-07-01', '1002-07-02'], 'name' => 'Stamp Duty (Provincial Council)'],
                        ['codes' => ['1002-09-00'], 'name' => 'Turn Over Tax'],
                        ['codes' => ['1003-07-09', '1003-07-10', '1003-07-12', '1003-03-01'], 'name' => 'Other Licence Fees'],
                    ],
                    'subtotal' => true,
                    'section_key' => 'A'
                ],
                'B' => [
                    'title' => 'Return on Government Assets & rent',
                    'items' => [
                        ['codes' => ['2002-01-01'], 'name' => 'Rent on Land & Government Building'],
                        ['codes' => ['2002-02-02'], 'name' => 'Interest'],
                        ['codes' => ['2002-02-03'], 'name' => 'Interest On Investments'],
                        ['codes' => ['2002-01-03', '2002-01-05'], 'name' => 'others'],
                    ],
                    'subtotal' => true,
                    'section_key' => 'B'
                ],
                'C' => [
                    'title' => 'Sales & Charges',
                    'items' => [
                        ['codes' => [], 'name' => 'Agriculture Activities'],
                        ['codes' => [], 'name' => 'Health service'],
                        ['codes' => ['2003-02-22'], 'name' => 'Net Profit of commercial advance accounts'],
                        ['codes' => ['2003-02-14'], 'name' => 'Fees Under Motor Traffic Act'],
                        ['codes' => ['2003-02-29'], 'name' => 'Registration of Companies Activities / Business names'],
                        ['codes' => ['2003-03-03'], 'name' => 'Fees Under Private Omnibus Act'],
                        ['codes' => ['2003-02-06'], 'name' => 'Fees Under Fauna & Flora Protection Ordinance (Timber Permit)'],
                        ['codes' => ['2003-99-00', '2003-99-01', '2003-99-02', '2003-99-03', '2003-99-04', '2003-02-13', '2003-02-21', '2003-02-30', '2003-02-28', '2003-02-24'], 'name' => 'Others'],
                    ],
                    'subtotal' => true,
                    'section_key' => 'C'
                ],
                'D' => [
                    'title' => 'Sales of Capital Assets',
                    'items' => [
                        ['codes' => ['2006-02-00'], 'name' => 'Sales of Capital Assets'],
                    ],
                    'subtotal' => true,
                    'section_key' => 'D'
                ],
                'E' => [
                    'title' => 'Total Devolved Revenue (A+B+C+D)',
                    'items' => [],
                    'subtotal' => true,
                    'is_total' => true,
                    'section_key' => 'E'
                ],
                'F' => [
                    'title' => 'Revenue other than Devolved',
                    'items' => [
                        ['codes' => ['1002-07-00'], 'name' => 'Stamp Duty (Transferred by Central Gov.)'],
                        ['codes' => ['1002-12-00'], 'name' => 'NBT (Transferred by Central Gov.)'],
                        ['codes' => ['1002-05-04'], 'name' => 'Vehicle Registration (Transferred by Central Gov.)'],
                        ['codes' => ['2003-03-01'], 'name' => 'Court Fines & Fees (Transferred to LA)'],
                    ],
                    'subtotal' => true,
                    'section_key' => 'F'
                ]
            ];

            // Get provincial estimate from estimates table
            $estimates = Estimate::select('head', 'program', 'project', 'sub_project', 'object', 'estimate', 'revenue_code_name')
                ->get();

            $estimateLookup = [];
            foreach ($estimates as $estimate) {
                $code = $this->formatCombinedCode($estimate);
                $estimateLookup[$code] = $estimate;
            }

            // Process data
            $resultData = [];
            $sectionSubtotals = [];

            // Process sections A, B, C, D
            foreach (['A', 'B', 'C', 'D'] as $sectionKey) {
                $section = $revenueStructure[$sectionKey];
                $sectionItems = [];
                $sectionTotalRevenue = 0;
                $sectionTotalScheduled = 0;
                $sectionAll = 0;
                $sectionAllScheduled = 0;
                $sectionPreviousMonth = 0;
                $sectionScheduledPrevious = 0;

                foreach ($section['items'] as $item) {
                    $codes = $item['codes'] ?? [];
                    $name = $item['name'];

                    $totalRevenue = 0;
                    $totalPreviousMonth = 0;
                    $totalScheduled = 0;
                    $totalScheduledMonthly = 0;

                    // Get data for each code and sum up
                    foreach ($codes as $code) {
                        $revenueValue = 0;
                        $previousMonthValue = 0;
                        $scheduledTarget = 0;
                        $scheduledMonthly = 0;

                        if ($code !== '') {
                            // Parse code
                            $parts = explode('-', $code);
                            $head = $parts[0] ?? '';
                            $project = $parts[1] ?? '';
                            $object = $parts[2] ?? '';

                            // Get revenue value
                            $revenueValue = $this->getMonthlyValue(
                                $year,
                                $month,
                                $head,
                                $project,
                                $object
                            );

                            // Get previous month value
                            $prevMonth = $month - 1;
                            if ($prevMonth > 0) {
                                $previousMonthValue = $this->getMonthlyValue(
                                    $year,
                                    $prevMonth,
                                    $head,
                                    $project,
                                    $object
                                );
                            }

                            // Get scheduled target
                            if (isset($estimateLookup[$code])) {
                                $scheduledTarget = $estimateLookup[$code]->estimate ?? 0;
                                $scheduledMonthly = $scheduledTarget / 12;
                            }
                        }

                        $totalRevenue += $revenueValue;
                        $totalPreviousMonth += $previousMonthValue;
                        $totalScheduled += $scheduledTarget;
                        $totalScheduledMonthly += $scheduledMonthly;
                    }

                    // Calculate cumulative revenue
                    $cumulativeRevenue = $totalPreviousMonth + $totalRevenue;
                    
                    // Calculate scheduled values
                    $scheduledPrevious = $totalScheduledMonthly * ($month - 1);
                    $scheduledCurrent = $totalScheduledMonthly;
                    $cumulativeScheduled = $totalScheduledMonthly * $month;

                    $itemData = [
                        'code' => implode(', ', $codes),
                        'name' => $name,
                        'revenue_value' => round($totalRevenue, 2),
                        'previous_month' => round($totalPreviousMonth, 2),
                        'cumulative_revenue' => round($cumulativeRevenue, 2),
                        'scheduled_target' => round($totalScheduled, 2),
                        'scheduled_previous' => round($scheduledPrevious, 2),
                        'scheduled_current' => round($scheduledCurrent, 2),
                        'cumulative_scheduled' => round($cumulativeScheduled, 2),
                    ];

                    $sectionItems[] = $itemData;
                    $sectionTotalRevenue += $totalRevenue;
                    $sectionTotalScheduled += $totalScheduled;
                    $sectionAll += $cumulativeRevenue;
                    $sectionAllScheduled += $cumulativeScheduled;
                    $sectionPreviousMonth += $totalPreviousMonth;
                    $sectionScheduledPrevious += $scheduledPrevious;
                }

                // Add subtotal if applicable
                if ($section['subtotal'] ?? false) {
                    $sectionScheduledMonthly = $sectionTotalScheduled / 12;
                    $sectionScheduledPrev = $sectionScheduledMonthly * ($month - 1);
                    $sectionCumulativeScheduled = $sectionScheduledMonthly * $month;

                    $sectionItems[] = [
                        'is_subtotal' => true,
                        'name' => 'Sub total',
                        'revenue_value' => round($sectionTotalRevenue, 2),
                        'previous_month' => round($sectionPreviousMonth, 2),
                        'cumulative_revenue' => round($sectionAll, 2),
                        'scheduled_target' => round($sectionTotalScheduled, 2),
                        'scheduled_previous' => round($sectionScheduledPrev, 2),
                        'scheduled_current' => round($sectionScheduledMonthly, 2),
                        'cumulative_scheduled' => round($sectionCumulativeScheduled, 2),
                    ];

                    // Store subtotal for section E calculation
                    $sectionSubtotals[$sectionKey] = [
                        'total_revenue' => round($sectionTotalRevenue, 2),
                        'total_scheduled' => round($sectionTotalScheduled, 2),
                        'total_cumulative' => round($sectionAll, 2),
                        'total_cumulative_scheduled' => round($sectionCumulativeScheduled, 2),
                        'previous_month' => round($sectionPreviousMonth, 2),
                        'scheduled_previous' => round($sectionScheduledPrev, 2),
                        'scheduled_current' => round($sectionScheduledMonthly, 2),
                    ];
                }

                $resultData[$sectionKey] = [
                    'title' => $section['title'],
                    'items' => $sectionItems,
                    'total_revenue' => round($sectionTotalRevenue, 2),
                    'total_scheduled' => round($sectionTotalScheduled, 2),
                    'total_cumulative' => round($sectionAll, 2),
                    'total_cumulative_scheduled' => round($sectionAllScheduled, 2),
                    'total_previous_month' => round($sectionPreviousMonth, 2),
                    'is_total' => $section['is_total'] ?? false,
                    'section_key' => $section['section_key'] ?? $sectionKey,
                ];
            }

            // Calculate Section E = A + B + C + D
            $eTotalRevenue = 0;
            $eTotalScheduled = 0;
            $eTotalCumulative = 0;
            $eTotalCumulativeScheduled = 0;
            $ePreviousMonth = 0;
            $eScheduledPrevious = 0;
            $eScheduledCurrent = 0;

            foreach (['A', 'B', 'C', 'D'] as $key) {
                if (isset($sectionSubtotals[$key])) {
                    $sub = $sectionSubtotals[$key];
                    $eTotalRevenue += $sub['total_revenue'];
                    $eTotalScheduled += $sub['total_scheduled'];
                    $eTotalCumulative += $sub['total_cumulative'];
                    $eTotalCumulativeScheduled += $sub['total_cumulative_scheduled'];
                    $ePreviousMonth += $sub['previous_month'];
                    $eScheduledPrevious += $sub['scheduled_previous'];
                    $eScheduledCurrent += $sub['scheduled_current'];
                }
            }

            // Create Section E
            $eItems = [];
            $eItems[] = [
                'is_subtotal' => true,
                'name' => 'Total Devolved Revenue (A+B+C+D)',
                'revenue_value' => round($eTotalRevenue, 2),
                'previous_month' => round($ePreviousMonth, 2),
                'cumulative_revenue' => round($eTotalCumulative, 2),
                'scheduled_target' => round($eTotalScheduled, 2),
                'scheduled_previous' => round($eScheduledPrevious, 2),
                'scheduled_current' => round($eScheduledCurrent, 2),
                'cumulative_scheduled' => round($eTotalCumulativeScheduled, 2),
            ];

            $resultData['E'] = [
                'title' => 'Total Devolved Revenue (A+B+C+D)',
                'items' => $eItems,
                'total_revenue' => round($eTotalRevenue, 2),
                'total_scheduled' => round($eTotalScheduled, 2),
                'total_cumulative' => round($eTotalCumulative, 2),
                'total_cumulative_scheduled' => round($eTotalCumulativeScheduled, 2),
                'total_previous_month' => round($ePreviousMonth, 2),
                'is_total' => true,
                'section_key' => 'E'
            ];

            // Process Section F
            $sectionF = $revenueStructure['F'];
            $fItems = [];
            $fTotalRevenue = 0;
            $fTotalScheduled = 0;
            $fAll = 0;
            $fAllScheduled = 0;
            $fPreviousMonth = 0;
            $fScheduledPrevious = 0;

            foreach ($sectionF['items'] as $item) {
                $codes = $item['codes'] ?? [];
                $name = $item['name'];

                $totalRevenue = 0;
                $totalPreviousMonth = 0;
                $totalScheduled = 0;
                $totalScheduledMonthly = 0;

                foreach ($codes as $code) {
                    $revenueValue = 0;
                    $previousMonthValue = 0;
                    $scheduledTarget = 0;
                    $scheduledMonthly = 0;

                    if ($code !== '') {
                        $parts = explode('-', $code);
                        $head = $parts[0] ?? '';
                        $project = $parts[1] ?? '';
                        $object = $parts[2] ?? '';

                        $revenueValue = $this->getMonthlyValue($year, $month, $head, $project, $object);

                        $prevMonth = $month - 1;
                        if ($prevMonth > 0) {
                            $previousMonthValue = $this->getMonthlyValue($year, $prevMonth, $head, $project, $object);
                        }

                        if (isset($estimateLookup[$code])) {
                            $scheduledTarget = $estimateLookup[$code]->estimate ?? 0;
                            $scheduledMonthly = $scheduledTarget / 12;
                        }
                    }

                    $totalRevenue += $revenueValue;
                    $totalPreviousMonth += $previousMonthValue;
                    $totalScheduled += $scheduledTarget;
                    $totalScheduledMonthly += $scheduledMonthly;
                }

                $cumulativeRevenue = $totalPreviousMonth + $totalRevenue;
                $scheduledPrevious = $totalScheduledMonthly * ($month - 1);
                $scheduledCurrent = $totalScheduledMonthly;
                $cumulativeScheduled = $totalScheduledMonthly * $month;

                $fItems[] = [
                    'code' => implode(', ', $codes),
                    'name' => $name,
                    'revenue_value' => round($totalRevenue, 2),
                    'previous_month' => round($totalPreviousMonth, 2),
                    'cumulative_revenue' => round($cumulativeRevenue, 2),
                    'scheduled_target' => round($totalScheduled, 2),
                    'scheduled_previous' => round($scheduledPrevious, 2),
                    'scheduled_current' => round($scheduledCurrent, 2),
                    'cumulative_scheduled' => round($cumulativeScheduled, 2),
                ];

                $fTotalRevenue += $totalRevenue;
                $fTotalScheduled += $totalScheduled;
                $fAll += $cumulativeRevenue;
                $fAllScheduled += $cumulativeScheduled;
                $fPreviousMonth += $totalPreviousMonth;
                $fScheduledPrevious += $scheduledPrevious;
            }

            // Add F subtotal
            $fScheduledMonthly = $fTotalScheduled / 12;
            $fScheduledPrev = $fScheduledMonthly * ($month - 1);
            $fCumulativeScheduled = $fScheduledMonthly * $month;

            $fItems[] = [
                'is_subtotal' => true,
                'name' => 'Sub total',
                'revenue_value' => round($fTotalRevenue, 2),
                'previous_month' => round($fPreviousMonth, 2),
                'cumulative_revenue' => round($fAll, 2),
                'scheduled_target' => round($fTotalScheduled, 2),
                'scheduled_previous' => round($fScheduledPrev, 2),
                'scheduled_current' => round($fScheduledMonthly, 2),
                'cumulative_scheduled' => round($fCumulativeScheduled, 2),
            ];

            $resultData['F'] = [
                'title' => 'Revenue other than Devolved',
                'items' => $fItems,
                'total_revenue' => round($fTotalRevenue, 2),
                'total_scheduled' => round($fTotalScheduled, 2),
                'total_cumulative' => round($fAll, 2),
                'total_cumulative_scheduled' => round($fAllScheduled, 2),
                'total_previous_month' => round($fPreviousMonth, 2),
                'section_key' => 'F'
            ];

            // Calculate Grand Total = E + F
            $grandTotalRevenue = $eTotalRevenue + $fTotalRevenue;
            $grandTotalScheduled = $eTotalScheduled + $fTotalScheduled;
            $grandTotalCumulative = $eTotalCumulative + $fAll;
            $grandTotalCumulativeScheduled = $eTotalCumulativeScheduled + $fCumulativeScheduled;

            // Add Grand Total
            $resultData['GRAND_TOTAL'] = [
                'title' => 'Grand Total (E+F)',
                'items' => [],
                'total_revenue' => round($grandTotalRevenue, 2),
                'total_scheduled' => round($grandTotalScheduled, 2),
                'total_cumulative' => round($grandTotalCumulative, 2),
                'total_cumulative_scheduled' => round($grandTotalCumulativeScheduled, 2),
                'is_grand_total' => true,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'report_data' => $resultData,
                    'year' => $year,
                    'month' => $month,
                    'month_name' => $monthNames[$month] ?? $month,
                    'province' => 'Southern',
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in ActualRevenueReport getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get monthly value for a specific code
     */
    private function getMonthlyValue($year, $month, $head, $project, $object)
    {
        $total = 0;

        // Get from Treasury table
        $treasuryValue = Treasury::whereYear('created_at', $year)
            ->where('month', $month)
            ->where('head', $head)
            ->where('project', $project)
            ->where('object', $object)
            ->where('dr_cr_code', 4000)
            ->where('dr_cr', 'CR')
            ->sum('cash_xe');

        $total += $treasuryValue;

        // Get from MonthlyFincance table
        $monthlyValue = MonthlyFincance::whereYear('created_at', $year)
            ->where('month', $month)
            ->where('head', $head)
            ->where('project', $project)
            ->where('object', $object)
            ->where('dr_cr_code', 4000)
            ->where('dr_cr', 'CR')
            ->sum('cash_xe');

        $total += $monthlyValue;

        return round($total, 2);
    }

    /**
     * Format combined code: Head-Project-Object
     */
    private function formatCombinedCode($record)
    {
        $head = $record->head ?? '';
        $project = $record->project !== null ? str_pad($record->project, 2, '0', STR_PAD_LEFT) : '';
        $object = $record->object !== null ? str_pad($record->object, 2, '0', STR_PAD_LEFT) : '';
        
        return $head . '-' . $project . '-' . $object;
    }

    /**
     * Get filter options
     */
    public function getFilterOptions(Request $request)
    {
        try {
            $years = Treasury::select(DB::raw('DISTINCT YEAR(created_at) as year'))
                ->whereNotNull('created_at')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            $years2 = MonthlyFincance::select(DB::raw('DISTINCT YEAR(created_at) as year'))
                ->whereNotNull('created_at')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            $allYears = $years->merge($years2)->unique()->sortDesc()->values();

            if ($allYears->isEmpty()) {
                $currentYear = date('Y');
                $allYears = collect(range($currentYear - 5, $currentYear))->sortDesc()->values();
            }

            $months = collect(range(1, 12));

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $allYears,
                    'months' => $months,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in ActualRevenueReport getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export to PDF
     */
    public function exportPdf(Request $request)
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

            $data = $this->getData($request);
            $responseData = $data->getData();

            if (!$responseData->success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data for export'
                ], 500);
            }

            $reportData = $responseData->data->report_data;
            $monthName = $responseData->data->month_name;
            $year = $responseData->data->year;
            $province = $responseData->data->province;

            return response()->json([
                'success' => true,
                'data' => [
                    'report_data' => $reportData,
                    'year' => $year,
                    'month_name' => $monthName,
                    'province' => $province,
                ],
                'title' => "Monthly_Revenue_Report_{$year}_{$monthName}"
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in ActualRevenueReport exportPdf: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}