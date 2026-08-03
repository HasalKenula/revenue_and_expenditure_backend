<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueRefundByTrnoController extends Controller
{
    /**
     * Get Revenue Refund Account Report grouped by TRNO with month-wise data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');

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

            // Get all unique TRNOs from monthly_fincances table for the selected year
            // with dr_cr_code=5000 and dr_cr='DR' (refund)
            $trnos = MonthlyFincance::whereYear('created_at', $year)
                ->where('dr_cr_code', 5000)
                ->where('dr_cr', 'DR')
                ->select('trno')
                ->distinct()
                ->orderBy('trno')
                ->pluck('trno')
                ->values();

            // If no TRNOs found, return empty result
            if ($trnos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'records' => [],
                        'totals' => [],
                        'month_names' => $monthNames,
                        'filters' => [
                            'year' => $year
                        ]
                    ]
                ]);
            }

            $results = [];
            $grandTotalRefund = 0;

            foreach ($trnos as $trno) {
                // Get first record for this TRNO to get head, program, etc.
                $firstRecord = MonthlyFincance::whereYear('created_at', $year)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', 5000)
                    ->where('dr_cr', 'DR')
                    ->first();

                if (!$firstRecord) {
                    continue;
                }

                // Get revenue code name from estimates table
                $estimate = Estimate::where('head', $firstRecord->head)
                    ->where('program', $firstRecord->program)
                    ->where('project', $firstRecord->project)
                    ->where('sub_project', $firstRecord->sub_project)
                    ->where('object', $firstRecord->object)
                    ->first();

                // Store the record data for each month
                $monthlyValues = [];
                $trnoTotalRefund = 0;

                for ($month = 1; $month <= 12; $month++) {
                    $value = MonthlyFincance::whereYear('created_at', $year)
                        ->where('trno', $trno)
                        ->where('month', $month)
                        ->where('dr_cr_code', 5000)
                        ->where('dr_cr', 'DR')
                        ->sum('cash_xe');

                    if ($value > 0) {
                        $monthlyValues[] = [
                            'month' => $month,
                            'month_name' => $monthNames[$month] ?? $month,
                            'amount' => round($value, 2)
                        ];
                        $trnoTotalRefund += $value;
                    }
                }

                // Only include rows that have at least one month with value > 0
                if (!empty($monthlyValues)) {
                    // Sort by month
                    usort($monthlyValues, function($a, $b) {
                        return $a['month'] <=> $b['month'];
                    });

                    // For each month with refund, create a separate row
                    foreach ($monthlyValues as $monthData) {
                        $resultRow = [
                            'trno' => $trno,
                            'revenue_code_name' => $estimate ? $estimate->revenue_code_name : null,
                            'head' => $firstRecord->head,
                            'program' => $firstRecord->program,
                            'project' => $firstRecord->project,
                            'sub_project' => $firstRecord->sub_project,
                            'object' => $firstRecord->object,
                            'refund_amount' => $monthData['amount'],
                            'month_name' => $monthData['month_name'],
                            'month' => $monthData['month'],
                            'is_subtotal' => false
                        ];
                        $results[] = $resultRow;
                    }

                    // Add subtotal row for this TRNO
                    $subtotalRow = [
                        'trno' => $trno,
                        'revenue_code_name' => 'SUB TOTAL',
                        'head' => null,
                        'program' => null,
                        'project' => null,
                        'sub_project' => null,
                        'object' => null,
                        'refund_amount' => round($trnoTotalRefund, 2),
                        'month_name' => '',
                        'month' => null,
                        'is_subtotal' => true
                    ];
                    $results[] = $subtotalRow;

                    $grandTotalRefund += $trnoTotalRefund;
                }
            }

            // Calculate totals
            $totals = [
                'total_records' => count($results),
                'total_refund' => round($grandTotalRefund, 2)
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => $totals,
                    'filters' => [
                        'year' => $year
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in RevenueRefundByTrno getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get filter options (years)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from created_at in MonthlyFincance table
            $years = MonthlyFincance::select(DB::raw('DISTINCT YEAR(created_at) as year'))
                ->whereNotNull('created_at')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            // If no years found, provide default range
            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear))->sortDesc()->values();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in RevenueRefundByTrno getFilterOptions: ' . $e->getMessage());
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
    //         $headers = ['TRNO', 'Revenue Code Name', 'Revenue Code', 'Refund Amount (Rs)', 'Month'];

    //         $csvRows = [];
    //         $csvRows[] = implode(',', $headers);

    //         // Add data rows
    //         foreach ($records as $record) {
    //             $revenueCode = '';
    //             if (!$record['is_subtotal']) {
    //                 $revenueCode = ($record['head'] ?? '') . '-' . ($record['program'] ?? '') . '-' .
    //                                ($record['project'] ?? '') . '-' . ($record['sub_project'] ?? '') . '-' .
    //                                ($record['object'] ?? '');
    //             }

    //             $row = [
    //                 $record['trno'] ?? '',
    //                 $record['revenue_code_name'] ?? '',
    //                 $revenueCode,
    //                 number_format($record['refund_amount'] ?? 0, 2),
    //                 $record['month_name'] ?? ''
    //             ];

    //             $csvRows[] = implode(',', $row);
    //         }

    //         // Generate CSV
    //         $csvContent = implode("\n", $csvRows);

    //         return response($csvContent)
    //             ->header('Content-Type', 'text/csv')
    //             ->header('Content-Disposition', "attachment; filename=revenue_refund_by_trno_{$year}.csv");

    //     } catch (\Exception $e) {
    //         \Log::error('Error in RevenueRefundByTrno exportCsv: ' . $e->getMessage());
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
            $headers = ['Head', 'Revenue Code Name', 'Revenue Code', 'Refund Amount (Rs)', 'Month'];

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
                // Check if it's a subtotal row
                if (isset($record['is_subtotal']) && $record['is_subtotal'] === true) {
                    return '';
                }
                
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
                $revenueCode = '';
                if (!($record['is_subtotal'] ?? false)) {
                    $revenueCode = $formatCombinedCode($record);
                }

                $row = [
                    '"' . ($record['trno'] ?? '') . '"',
                    '"' . ($record['revenue_code_name'] ?? '') . '"',
                    '"' . $revenueCode . '"',
                    $formatNumber($record['refund_amount'] ?? 0),
                    '"' . ($record['month_name'] ?? '') . '"'
                ];

                $csvRows[] = implode(',', $row);
            }

            // Add totals row if totals exist
            if (!empty($totals)) {
                $totalRow = [
                    '"GRAND TOTAL"',
                    '',
                    '',
                    $formatNumber($totals['total_refund_amount'] ?? 0),
                    ''
                ];
                $csvRows[] = implode(',', $totalRow);
            }

            // Add empty row and summary
            $csvRows[] = '';
            $csvRows[] = '"Report generated on: ' . date('Y-m-d H:i:s') . '"';
            $csvRows[] = '"Year: ' . $year . '"';
            $csvRows[] = '"Total Records: ' . count($records) . '"';

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            // Add BOM for UTF-8 Excel compatibility
            $csvContent = "\xEF\xBB\xBF" . $csvContent;

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=revenue_refund_by_trno_{$year}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in RevenueRefundByTrno exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}