<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Treasury;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxRevenueController extends Controller
{
    /**
     * Get Tax Revenue Report data
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

            // Get all months from January to selected month
            $months = range(1, (int)$month);
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Get all estimates with head between 1000 and 2000
            $estimates = Estimate::where('head', '>', 1000)
                ->where('head', '<', 2000)
                ->orderBy('head')
                ->orderBy('program')
                ->orderBy('project')
                ->orderBy('sub_project')
                ->orderBy('object')
                ->get();

            $results = [];

            foreach ($estimates as $estimate) {
                $xEntryTotal = 0;
                $collectionTotal = 0;
                $refundTotal = 0;

                // Get cumulative values for all months from January to selected month
                foreach ($months as $monthNum) {
                    // X Entry: From monthly_fincances (dr_cr_code=4000, dr_cr=CR)
                    $xEntryValue = $this->getMonthlyFincanceValue(
                        $year,
                        $monthNum,
                        $estimate->head,
                        $estimate->program,
                        $estimate->project,
                        $estimate->sub_project,
                        $estimate->object,
                        4000,
                        'CR'
                    );
                    $xEntryTotal += $xEntryValue;

                    // Collection: From treasury (dr_cr_code=4000, dr_cr=CR)
                    $collectionValue = $this->getTreasuryValue(
                        $year,
                        $monthNum,
                        $estimate->head,
                        $estimate->program,
                        $estimate->project,
                        $estimate->sub_project,
                        $estimate->object,
                        4000,
                        'CR'
                    );
                    $collectionTotal += $collectionValue;

                    // Refund: From both treasury and monthly_fincances (dr_cr_code=5000, dr_cr=DR)
                    $refundValue = $this->getRefundValue(
                        $year,
                        $monthNum,
                        $estimate->head,
                        $estimate->program,
                        $estimate->project,
                        $estimate->sub_project,
                        $estimate->object
                    );
                    $refundTotal += $refundValue;
                }

                $row = [
                    'head' => $estimate->head,
                    'program' => $estimate->program,
                    'project' => $estimate->project,
                    'sub_project' => $estimate->sub_project,
                    'object' => $estimate->object,
                    'revenue_code_name' => $estimate->revenue_code_name,
                    'x_entry_total' => round($xEntryTotal, 2),
                    'collection_total' => round($collectionTotal, 2),
                    'refund_total' => round($refundTotal, 2),
                    'net_revenue' => round($xEntryTotal + $collectionTotal - $refundTotal, 2)
                ];

                $results[] = $row;
            }

            // Calculate totals
            $totals = $this->calculateTotals($results);

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => $totals,
                    'month' => $month,
                    'month_name' => $monthNames[$month] ?? $month,
                    'filters' => [
                        'year' => $year,
                        'month' => $month
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in TaxRevenue getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get value from monthly_fincances table
     */
    private function getMonthlyFincanceValue($year, $month, $head, $program, $project, $sub_project, $object, $drCrCode, $drCr)
    {
        return MonthlyFincance::whereYear('created_at', $year)
            ->where('month', $month)
            ->where('head', $head)
            ->where('program', $program)
            ->where('project', $project)
            ->where('sub_project', $sub_project)
            ->where('object', $object)
            ->where('dr_cr_code', $drCrCode)
            ->where('dr_cr', $drCr)
            ->sum('cash_xe');
    }

    /**
     * Get value from treasury table
     */
    private function getTreasuryValue($year, $month, $head, $program, $project, $sub_project, $object, $drCrCode, $drCr)
    {
        return Treasury::whereYear('created_at', $year)
            ->where('month', $month)
            ->where('head', $head)
            ->where('program', $program)
            ->where('project', $project)
            ->where('sub_project', $sub_project)
            ->where('object', $object)
            ->where('dr_cr_code', $drCrCode)
            ->where('dr_cr', $drCr)
            ->sum('cash_xe');
    }

    /**
     * Get refund value from both treasury and monthly_fincances
     */
    private function getRefundValue($year, $month, $head, $program, $project, $sub_project, $object)
    {
        $treasuryRefund = Treasury::whereYear('created_at', $year)
            ->where('month', $month)
            ->where('head', $head)
            ->where('program', $program)
            ->where('project', $project)
            ->where('sub_project', $sub_project)
            ->where('object', $object)
            ->where('dr_cr_code', 5000)
            ->where('dr_cr', 'DR')
            ->sum('cash_xe');

        $monthlyRefund = MonthlyFincance::whereYear('created_at', $year)
            ->where('month', $month)
            ->where('head', $head)
            ->where('program', $program)
            ->where('project', $project)
            ->where('sub_project', $sub_project)
            ->where('object', $object)
            ->where('dr_cr_code', 5000)
            ->where('dr_cr', 'DR')
            ->sum('cash_xe');

        return round($treasuryRefund + $monthlyRefund, 2);
    }

    /**
     * Calculate totals
     */
    private function calculateTotals($results)
    {
        $totals = [
            'x_entry_total' => 0,
            'collection_total' => 0,
            'refund_total' => 0,
            'net_revenue' => 0
        ];

        foreach ($results as $row) {
            $totals['x_entry_total'] += $row['x_entry_total'] ?? 0;
            $totals['collection_total'] += $row['collection_total'] ?? 0;
            $totals['refund_total'] += $row['refund_total'] ?? 0;
            $totals['net_revenue'] += $row['net_revenue'] ?? 0;
        }

        // Round values
        $totals['x_entry_total'] = round($totals['x_entry_total'], 2);
        $totals['collection_total'] = round($totals['collection_total'], 2);
        $totals['refund_total'] = round($totals['refund_total'], 2);
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
            \Log::error('Error in TaxRevenue getFilterOptions: ' . $e->getMessage());
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

    //         if (!$year || !$month) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Year and month are required'
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
    //         $monthName = $responseData->data->month_name;

    //         // Prepare CSV headers
    //         $headers = [
    //             'Revenue Code',
    //             'Revenue Category',
    //             'Total X Entry (Rs)',
    //             'Total Collection (Rs)',
    //             'Total Refund (Rs)',
    //             'Net Revenue (Rs)'
    //         ];

    //         $csvRows = [];
    //         $csvRows[] = implode(',', $headers);

    //         // Add data rows
    //         foreach ($records as $record) {
    //             $row = [
    //                 ($record['head'] ?? '') . '-' . ($record['program'] ?? '') . '-' .
    //                 ($record['project'] ?? '') . '-' . ($record['sub_project'] ?? '') . '-' .
    //                 ($record['object'] ?? ''),
    //                 $record['revenue_code_name'] ?? '',
    //                 number_format($record['x_entry_total'] ?? 0, 2),
    //                 number_format($record['collection_total'] ?? 0, 2),
    //                 number_format($record['refund_total'] ?? 0, 2),
    //                 number_format($record['net_revenue'] ?? 0, 2)
    //             ];

    //             $csvRows[] = implode(',', $row);
    //         }

    //         // Add totals row
    //         $totalRow = [
    //             'TOTAL',
    //             '',
    //             number_format($totals['x_entry_total'] ?? 0, 2),
    //             number_format($totals['collection_total'] ?? 0, 2),
    //             number_format($totals['refund_total'] ?? 0, 2),
    //             number_format($totals['net_revenue'] ?? 0, 2)
    //         ];
    //         $csvRows[] = implode(',', $totalRow);

    //         // Generate CSV
    //         $csvContent = implode("\n", $csvRows);

    //         return response($csvContent)
    //             ->header('Content-Type', 'text/csv')
    //             ->header('Content-Disposition', "attachment; filename=tax_revenue_{$year}_{$monthName}.csv");

    //     } catch (\Exception $e) {
    //         \Log::error('Error in TaxRevenue exportCsv: ' . $e->getMessage());
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

            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
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
            $monthName = $responseData->data->month_name ?? '';

            // Prepare CSV headers with quotes
            $headers = [
                'Revenue Code',
                'Revenue Category',
                'Total X Entry (Rs)',
                'Total Collection (Rs)',
                'Total Refund (Rs)',
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

            // Format combined code with apostrophe to prevent Excel date conversion
            $formatCombinedCode = function($record) {
                $head = (string)($record['head'] ?? '');
                $program = (string)($record['program'] ?? '');
                $project = (string)($record['project'] ?? '');
                $subProject = (string)($record['sub_project'] ?? '');
                $object = (string)($record['object'] ?? '');
                
                // Format each part
                $formatPart = function($value, $length = 2) {
                    if ($value === '' || $value === null) {
                        return str_repeat('0', $length);
                    }
                    $value = trim($value);
                    if (is_numeric($value)) {
                        return str_pad($value, $length, '0', STR_PAD_LEFT);
                    }
                    return $value;
                };
                
                $formattedHead = $head ?: '0';
                $formattedProject = $formatPart($project, 2);
                $formattedObject = $formatPart($object, 2);
                
                $code = "{$formattedHead}-{$formattedProject}-{$formattedObject}";
                
                // Add apostrophe to prevent Excel from converting to date
                return " " . $code;
            };

            // Add data rows
            foreach ($records as $record) {
                $row = [
                    '"' . $formatCombinedCode($record) . '"',
                    '"' . ($record['revenue_code_name'] ?? '') . '"',
                    $formatNumber($record['x_entry_total'] ?? 0),
                    $formatNumber($record['collection_total'] ?? 0),
                    $formatNumber($record['refund_total'] ?? 0),
                    $formatNumber($record['net_revenue'] ?? 0)
                ];

                $csvRows[] = implode(',', $row);
            }

            // Add totals row
            $totalRow = [
                '"TOTAL"',
                '',
                $formatNumber($totals['x_entry_total'] ?? 0),
                $formatNumber($totals['collection_total'] ?? 0),
                $formatNumber($totals['refund_total'] ?? 0),
                $formatNumber($totals['net_revenue'] ?? 0)
            ];
            $csvRows[] = implode(',', $totalRow);

            // Add empty row and summary
            $csvRows[] = '';
            $csvRows[] = '"Report generated on: ' . date('Y-m-d H:i:s') . '"';
            $csvRows[] = '"Period: ' . $monthName . ' ' . $year . '"';
            $csvRows[] = '"Total Records: ' . count($records) . '"';

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            // Add BOM for UTF-8 Excel compatibility
            $csvContent = "\xEF\xBB\xBF" . $csvContent;

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=tax_revenue_{$year}_{$monthName}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in TaxRevenue exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}