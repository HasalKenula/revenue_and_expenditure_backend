<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Treasury;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonthlySummaryController extends Controller
{
    /**
     * Get Monthly Summary Report data
     */
    public function getData(Request $request)
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

            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Determine which months to show
            $monthsToShow = [];
            if ($month && $month > 0) {
                // Show only selected month
                $monthsToShow = [(int)$month];
            } else {
                // Show all months (1-12)
                $monthsToShow = range(1, 12);
            }

            $results = [];

            foreach ($monthsToShow as $monthNum) {
                // X Entry: From monthly_fincances table (dr_cr_code=4000, dr_cr=CR)
                $xEntry = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $monthNum)
                    ->where('dr_cr_code', 4000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Collection: From treasury table (dr_cr_code=4000, dr_cr=CR)
                $collection = Treasury::whereYear('created_at', $year)
                    ->where('month', $monthNum)
                    ->where('dr_cr_code', 4000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Total = X Entry + Collection
                $total = $xEntry + $collection;

                // Refund: From both treasury and monthly_fincances (dr_cr_code=5000, dr_cr=DR)
                $refundTreasury = Treasury::whereYear('created_at', $year)
                    ->where('month', $monthNum)
                    ->where('dr_cr_code', 5000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $refundMonthly = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $monthNum)
                    ->where('dr_cr_code', 5000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $refund = $refundTreasury + $refundMonthly;

                // Net Revenue = Total - Refund
                $netRevenue = $total - $refund;

                $results[] = [
                    'month' => $monthNum,
                    'month_name' => $monthNames[$monthNum] ?? $monthNum,
                    'x_entry' => round($xEntry, 2),
                    'collection' => round($collection, 2),
                    'total' => round($total, 2),
                    'refund' => round($refund, 2),
                    'net_revenue' => round($netRevenue, 2)
                ];
            }

            // Calculate totals
            $totals = $this->calculateTotals($results);

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => $totals,
                    'filters' => [
                        'year' => $year,
                        'month' => $month
                    ],
                    'month_names' => $monthNames
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in MonthlySummary getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Calculate totals
     */
    private function calculateTotals($results)
    {
        $totals = [
            'x_entry' => 0,
            'collection' => 0,
            'total' => 0,
            'refund' => 0,
            'net_revenue' => 0
        ];

        foreach ($results as $row) {
            $totals['x_entry'] += $row['x_entry'] ?? 0;
            $totals['collection'] += $row['collection'] ?? 0;
            $totals['total'] += $row['total'] ?? 0;
            $totals['refund'] += $row['refund'] ?? 0;
            $totals['net_revenue'] += $row['net_revenue'] ?? 0;
        }

        // Round values
        $totals['x_entry'] = round($totals['x_entry'], 2);
        $totals['collection'] = round($totals['collection'], 2);
        $totals['total'] = round($totals['total'], 2);
        $totals['refund'] = round($totals['refund'], 2);
        $totals['net_revenue'] = round($totals['net_revenue'], 2);

        return $totals;
    }

    /**
     * Get filter options (years and months)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from created_at in Treasury table
            $years = Treasury::select(DB::raw('DISTINCT YEAR(created_at) as year'))
                ->whereNotNull('created_at')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            // Also get from MonthlyFincance
            $years2 = MonthlyFincance::select(DB::raw('DISTINCT YEAR(created_at) as year'))
                ->whereNotNull('created_at')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            // Merge and get unique years
            $allYears = $years->merge($years2)->unique()->sortDesc()->values();

            // If no years found, provide default range
            if ($allYears->isEmpty()) {
                $currentYear = date('Y');
                $allYears = collect(range($currentYear - 5, $currentYear))->sortDesc()->values();
            }

            // Months 1-12
            $months = collect(range(1, 12));

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $allYears,
                    'months' => $months,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in MonthlySummary getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV
     */
    // public function exportCsv(Request $request)
    // {
    //     try {
    //         $year = $request->input('year');
    //         $month = $request->input('month');

    //         if (!$year) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Year is required'
    //             ], 422);
    //         }

    //         // Get data
    //         $data = $this->getData($request);
    //         $responseData = $data->getData();

    //         if (!$responseData->success) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to fetch data for export'
    //             ], 500);
    //         }

    //         $records = $responseData->data->records;
    //         $totals = $responseData->data->totals;

    //         // Prepare CSV headers
    //         $headers = [
    //             'Month',
    //             'X Entry (Rs)',
    //             'Collection (Rs)',
    //             'Total (Rs)',
    //             'Refund (Rs)',
    //             'Net Revenue (Rs)'
    //         ];

    //         $csvRows = [];
    //         $csvRows[] = implode(',', $headers);

    //         // Add data rows
    //         foreach ($records as $record) {
    //             $row = [
    //                 $record['month_name'],
    //                 number_format($record['x_entry'] ?? 0, 2),
    //                 number_format($record['collection'] ?? 0, 2),
    //                 number_format($record['total'] ?? 0, 2),
    //                 number_format($record['refund'] ?? 0, 2),
    //                 number_format($record['net_revenue'] ?? 0, 2)
    //             ];

    //             $csvRows[] = implode(',', $row);
    //         }

    //         // Add totals row
    //         $totalRow = [
    //             'TOTAL',
    //             number_format($totals['x_entry'] ?? 0, 2),
    //             number_format($totals['collection'] ?? 0, 2),
    //             number_format($totals['total'] ?? 0, 2),
    //             number_format($totals['refund'] ?? 0, 2),
    //             number_format($totals['net_revenue'] ?? 0, 2)
    //         ];
    //         $csvRows[] = implode(',', $totalRow);

    //         // Generate CSV
    //         $csvContent = implode("\n", $csvRows);

    //         return response($csvContent)
    //             ->header('Content-Type', 'text/csv')
    //             ->header('Content-Disposition', "attachment; filename=monthly_summary_{$year}.csv");

    //     } catch (\Exception $e) {
    //         \Log::error('Error in MonthlySummary exportCsv: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Export data to CSV
     */
    public function exportCsv(Request $request)
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

            // Get data
            $data = $this->getData($request);
            $responseData = $data->getData();

            if (!$responseData->success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data for export'
                ], 500);
            }

            // Convert stdClass objects to arrays
            $records = json_decode(json_encode($responseData->data->records), true);
            $totals = json_decode(json_encode($responseData->data->totals), true);

            // Prepare CSV headers with quotes
            $headers = [
                'Month',
                'X Entry (Rs)',
                'Collection (Rs)',
                'Total (Rs)',
                'Refund (Rs)',
                'Net Revenue (Rs)'
            ];

            $csvRows = [];
            
            // Add headers with quotes
            $csvRows[] = implode(',', array_map(function($h) { 
                return '"' . $h . '"'; 
            }, $headers));

            // Format number without commas (to prevent CSV splitting)
            $formatNumber = function($value) {
                if ($value === null || $value === '') return '0.00';
                return number_format((float)$value, 2, '.', '');
            };

            // Add data rows - Now accessing as arrays
            foreach ($records as $record) {
                $row = [
                    '"' . ($record['month_name'] ?? '') . '"',
                    $formatNumber($record['x_entry'] ?? 0),
                    $formatNumber($record['collection'] ?? 0),
                    $formatNumber($record['total'] ?? 0),
                    $formatNumber($record['refund'] ?? 0),
                    $formatNumber($record['net_revenue'] ?? 0)
                ];

                $csvRows[] = implode(',', $row);
            }

            // Add totals row
            $totalRow = [
                '"TOTAL"',
                $formatNumber($totals['x_entry'] ?? 0),
                $formatNumber($totals['collection'] ?? 0),
                $formatNumber($totals['total'] ?? 0),
                $formatNumber($totals['refund'] ?? 0),
                $formatNumber($totals['net_revenue'] ?? 0)
            ];
            $csvRows[] = implode(',', $totalRow);

            // Add empty row and summary
            $csvRows[] = '';
            $csvRows[] = '"Report generated on: ' . date('Y-m-d H:i:s') . '"';
            $csvRows[] = '"Year: ' . $year . '"';
            if ($month) {
                $monthNames = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                ];
                $csvRows[] = '"Month: ' . ($monthNames[$month] ?? $month) . '"';
            }
            $csvRows[] = '"Total Records: ' . count($records) . '"';

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            // Add BOM for UTF-8 Excel compatibility
            $csvContent = "\xEF\xBB\xBF" . $csvContent;

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=monthly_summary_{$year}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in MonthlySummary exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}