<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueCrossEntryByTrnoController extends Controller
{
    /**
     * Get Revenue Cross Entry by TRNO Report data
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

            // Get all unique combinations of TRNO, head, program, project, sub_project, object
            // from monthly_fincances table for the selected year
            $combinations = MonthlyFincance::whereYear('created_at', $year)
                ->where('dr_cr_code', 4000)
                ->where('dr_cr', 'CR')
                ->select(
                    'trno',
                    'head',
                    'program',
                    'project',
                    'sub_project',
                    'object'
                )
                ->distinct()
                ->orderBy('trno')
                ->orderBy('head')
                ->orderBy('program')
                ->orderBy('project')
                ->orderBy('sub_project')
                ->orderBy('object')
                ->get();

            // If no combinations found, return empty result
            if ($combinations->isEmpty()) {
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

            foreach ($combinations as $combo) {
                // Skip if trno is null
                if (is_null($combo->trno)) {
                    continue;
                }

                $row = [
                    'trno' => $combo->trno,
                    'head' => $combo->head,
                    'program' => $combo->program,
                    'project' => $combo->project,
                    'sub_project' => $combo->sub_project,
                    'object' => $combo->object,
                    'months' => [],
                    'total' => 0
                ];

                $totalAmount = 0;

                // Get monthly values for all 12 months for this combination
                for ($month = 1; $month <= 12; $month++) {
                    $value = MonthlyFincance::whereYear('created_at', $year)
                        ->where('month', $month)
                        ->where('trno', $combo->trno)
                        ->where('head', $combo->head)
                        ->where('program', $combo->program)
                        ->where('project', $combo->project)
                        ->where('sub_project', $combo->sub_project)
                        ->where('object', $combo->object)
                        ->where('dr_cr_code', 4000)
                        ->where('dr_cr', 'CR')
                        ->sum('cash_xe');

                    $row['months'][$month] = round($value, 2);
                    $totalAmount += $value;
                }

                $row['total'] = round($totalAmount, 2);

                // Only include rows that have at least one month with value > 0
                $hasData = false;
                foreach ($row['months'] as $monthValue) {
                    if ($monthValue > 0) {
                        $hasData = true;
                        break;
                    }
                }

                if ($hasData || $row['total'] > 0) {
                    // Get revenue code name from estimates table
                    $estimate = Estimate::where('head', $combo->head)
                        ->where('program', $combo->program)
                        ->where('project', $combo->project)
                        ->where('sub_project', $combo->sub_project)
                        ->where('object', $combo->object)
                        ->first();

                    $row['revenue_code_name'] = $estimate ? $estimate->revenue_code_name : null;
                    $results[] = $row;
                }
            }

            // Calculate totals for each month (overall totals)
            $totals = [
                'months' => [],
                'total' => 0
            ];

            for ($month = 1; $month <= 12; $month++) {
                $totals['months'][$month] = 0;
            }

            foreach ($results as $row) {
                foreach ($row['months'] as $month => $value) {
                    $totals['months'][$month] += $value;
                }
                $totals['total'] += $row['total'];
            }

            // Round totals
            foreach ($totals['months'] as $month => $value) {
                $totals['months'][$month] = round($value, 2);
            }
            $totals['total'] = round($totals['total'], 2);

            // Calculate TRNO subtotals for display
            $trnoSubtotals = [];
            foreach ($results as $row) {
                $trno = $row['trno'];
                if (!isset($trnoSubtotals[$trno])) {
                    $trnoSubtotals[$trno] = [
                        'months' => [],
                        'total' => 0
                    ];
                    for ($month = 1; $month <= 12; $month++) {
                        $trnoSubtotals[$trno]['months'][$month] = 0;
                    }
                }
                foreach ($row['months'] as $month => $value) {
                    $trnoSubtotals[$trno]['months'][$month] += $value;
                }
                $trnoSubtotals[$trno]['total'] += $row['total'];
            }

            // Round TRNO subtotals
            foreach ($trnoSubtotals as $trno => $subtotal) {
                foreach ($subtotal['months'] as $month => $value) {
                    $trnoSubtotals[$trno]['months'][$month] = round($value, 2);
                }
                $trnoSubtotals[$trno]['total'] = round($subtotal['total'], 2);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => $totals,
                    'trno_subtotals' => $trnoSubtotals,
                    'month_names' => $monthNames,
                    'filters' => [
                        'year' => $year
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in RevenueCrossEntryByTrno getData: ' . $e->getMessage());
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
            \Log::error('Error in RevenueCrossEntryByTrno getFilterOptions: ' . $e->getMessage());
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
    //         $trnoSubtotals = $responseData->data->trno_subtotals;
    //         $monthNames = $responseData->data->month_names;

    //         // Prepare CSV headers
    //         $headers = ['TRNO', 'Revenue Code Name', 'Revenue Code'];
    //         for ($i = 1; $i <= 12; $i++) {
    //             $headers[] = $monthNames[$i];
    //         }
    //         $headers[] = 'Total';

    //         $csvRows = [];
    //         $csvRows[] = implode(',', $headers);

    //         $previousTrno = null;

    //         // Add data rows
    //         foreach ($records as $record) {
    //             $row = [
    //                 $record['trno'] ?? '',
    //                 $record['revenue_code_name'] ?? '',
    //                 ($record['head'] ?? '') . '-' . ($record['program'] ?? '') . '-' .
    //                 ($record['project'] ?? '') . '-' . ($record['sub_project'] ?? '') . '-' .
    //                 ($record['object'] ?? '')
    //             ];

    //             for ($i = 1; $i <= 12; $i++) {
    //                 $row[] = number_format($record['months'][$i] ?? 0, 2);
    //             }

    //             $row[] = number_format($record['total'] ?? 0, 2);

    //             $csvRows[] = implode(',', $row);
    //         }

    //         // Add TRNO subtotals
    //         foreach ($trnoSubtotals as $trno => $subtotal) {
    //             $row = [$trno . ' SUBTOTAL', '', ''];
    //             for ($i = 1; $i <= 12; $i++) {
    //                 $row[] = number_format($subtotal['months'][$i] ?? 0, 2);
    //             }
    //             $row[] = number_format($subtotal['total'] ?? 0, 2);
    //             $csvRows[] = implode(',', $row);
    //         }

    //         // Add overall totals row
    //         $totalRow = ['GRAND TOTAL', '', ''];
    //         for ($i = 1; $i <= 12; $i++) {
    //             $totalRow[] = number_format($totals['months'][$i] ?? 0, 2);
    //         }
    //         $totalRow[] = number_format($totals['total'] ?? 0, 2);
    //         $csvRows[] = implode(',', $totalRow);

    //         // Generate CSV
    //         $csvContent = implode("\n", $csvRows);

    //         return response($csvContent)
    //             ->header('Content-Type', 'text/csv')
    //             ->header('Content-Disposition', "attachment; filename=revenue_cross_entry_by_trno_{$year}.csv");

    //     } catch (\Exception $e) {
    //         \Log::error('Error in RevenueCrossEntryByTrno exportCsv: ' . $e->getMessage());
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
            $trnoSubtotals = json_decode(json_encode($responseData->data->trno_subtotals), true);
            $monthNames = json_decode(json_encode($responseData->data->month_names), true);

            // Prepare CSV headers with quotes
            $headers = ['Head', 'Revenue Code Name', 'Revenue Code'];
            for ($i = 1; $i <= 12; $i++) {
                $headers[] = $monthNames[$i] ?? "Month $i";
            }
            $headers[] = 'Total';

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

            $previousTrno = null;

            // Add data rows
            foreach ($records as $record) {
                $row = [
                    '"' . ($record['trno'] ?? '') . '"',
                    '"' . ($record['revenue_code_name'] ?? '') . '"',
                    '"' . $formatCombinedCode($record) . '"'
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $row[] = $formatNumber($record['months'][$i] ?? 0);
                }

                $row[] = $formatNumber($record['total'] ?? 0);

                $csvRows[] = implode(',', $row);
            }

            // Add TRNO subtotals
            if (!empty($trnoSubtotals)) {
                foreach ($trnoSubtotals as $trno => $subtotal) {
                    $row = [
                        '"' . $trno . ' SUBTOTAL"',
                        '',
                        ''
                    ];
                    for ($i = 1; $i <= 12; $i++) {
                        $row[] = $formatNumber($subtotal['months'][$i] ?? 0);
                    }
                    $row[] = $formatNumber($subtotal['total'] ?? 0);
                    $csvRows[] = implode(',', $row);
                }
            }

            // Add overall totals row
            $totalRow = ['"GRAND TOTAL"', '', ''];
            for ($i = 1; $i <= 12; $i++) {
                $totalRow[] = $formatNumber($totals['months'][$i] ?? 0);
            }
            $totalRow[] = $formatNumber($totals['total'] ?? 0);
            $csvRows[] = implode(',', $totalRow);

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
                ->header('Content-Disposition', "attachment; filename=revenue_cross_entry_by_trno_{$year}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in RevenueCrossEntryByTrno exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}