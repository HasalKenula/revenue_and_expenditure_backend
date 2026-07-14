<?php

// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use App\Models\Estimate;
// use App\Models\Treasury;
// use App\Models\MonthlyFincance;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;

// class NetRevenueController extends Controller
// {
//     /**
//      * Get Net Revenue Report data
//      */
//     public function getData(Request $request)
//     {
//         try {
//             $year = $request->input('year');
//             $month = $request->input('month');

//             if (!$year || !$month) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Year and month are required'
//                 ], 422);
//             }

//             // Get all months from January to selected month
//             $months = range(1, (int)$month);
//             $monthNames = [
//                 1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
//                 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
//                 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
//             ];

//             // Get all estimates
//             $estimates = Estimate::orderBy('head')
//                 ->orderBy('program')
//                 ->orderBy('project')
//                 ->orderBy('sub_project')
//                 ->orderBy('object')
//                 ->get();

//             $results = [];

//             foreach ($estimates as $estimate) {
//                 $row = [
//                     'head' => $estimate->head,
//                     'program' => $estimate->program,
//                     'project' => $estimate->project,
//                     'sub_project' => $estimate->sub_project,
//                     'object' => $estimate->object,
//                     'revenue_code_name' => $estimate->revenue_code_name,
//                     'estimate' => $estimate->estimate,
//                     're_estimate' => $estimate->re_estimate,
//                     'months' => []
//                 ];

//                 // Get monthly values from treasury and monthly_fincances
//                 foreach ($months as $monthNum) {
//                     $value = $this->getMonthlyValue(
//                         $year,
//                         $monthNum,
//                         $estimate->head,
//                         $estimate->program,
//                         $estimate->project,
//                         $estimate->sub_project,
//                         $estimate->object
//                     );
//                     $row['months'][$monthNum] = $value;
//                 }

//                 $results[] = $row;
//             }

//             // Calculate totals
//             $totals = $this->calculateTotals($results, $months);

//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'records' => $results,
//                     'totals' => $totals,
//                     'months' => $months,
//                     'month_names' => $monthNames,
//                     'filters' => [
//                         'year' => $year,
//                         'month' => $month
//                     ]
//                 ]
//             ]);

//         } catch (\Exception $e) {
//             \Log::error('Error in NetRevenue getData: ' . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage(),
//                 'line' => $e->getLine()
//             ], 500);
//         }
//     }

//     /**
//      * Get monthly value from treasury and monthly_fincances tables
//      */
//     private function getMonthlyValue($year, $month, $head, $program, $project, $sub_project, $object)
//     {
//         $total = 0;

//         // Get from Treasury table
//         $treasuryValue = Treasury::whereYear('created_at', $year)
//             ->where('month', $month)
//             ->where('head', $head)
//             ->where('program', $program)
//             ->where('project', $project)
//             ->where('sub_project', $sub_project)
//             ->where('object', $object)
//             ->where('dr_cr_code', 4000)
//             ->where('dr_cr', 'CR')
//             ->sum('cash_xe');

//         $total += $treasuryValue;

//         // Get from MonthlyFincance table
//         $monthlyValue = MonthlyFincance::whereYear('created_at', $year)
//             ->where('month', $month)
//             ->where('head', $head)
//             ->where('program', $program)
//             ->where('project', $project)
//             ->where('sub_project', $sub_project)
//             ->where('object', $object)
//             ->where('dr_cr_code', 4000)
//             ->where('dr_cr', 'CR')
//             ->sum('cash_xe');

//         $total += $monthlyValue;

//         return round($total, 2);
//     }

//     /**
//      * Calculate totals for each month
//      */
//     private function calculateTotals($results, $months)
//     {
//         $totals = [
//             'estimate' => 0,
//             're_estimate' => 0,
//             'months' => []
//         ];

//         foreach ($months as $month) {
//             $totals['months'][$month] = 0;
//         }

//         foreach ($results as $row) {
//             $totals['estimate'] += $row['estimate'] ?? 0;
//             $totals['re_estimate'] += $row['re_estimate'] ?? 0;
            
//             foreach ($months as $month) {
//                 $totals['months'][$month] += $row['months'][$month] ?? 0;
//             }
//         }

//         // Round values
//         $totals['estimate'] = round($totals['estimate'], 2);
//         $totals['re_estimate'] = round($totals['re_estimate'], 2);
//         foreach ($totals['months'] as $key => $value) {
//             $totals['months'][$key] = round($value, 2);
//         }

//         return $totals;
//     }

//     /**
//      * Get filter options (years and months)
//      */
//     public function getFilterOptions(Request $request)
//     {
//         try {
//             // Get available years from created_at in Treasury table
//             $years = Treasury::select(DB::raw('DISTINCT YEAR(created_at) as year'))
//                 ->whereNotNull('created_at')
//                 ->orderBy('year', 'desc')
//                 ->pluck('year')
//                 ->values();

//             // Also get from MonthlyFincance
//             $years2 = MonthlyFincance::select(DB::raw('DISTINCT YEAR(created_at) as year'))
//                 ->whereNotNull('created_at')
//                 ->orderBy('year', 'desc')
//                 ->pluck('year')
//                 ->values();

//             // Merge and get unique years
//             $allYears = $years->merge($years2)->unique()->sortDesc()->values();

//             // If no years found, provide default range
//             if ($allYears->isEmpty()) {
//                 $currentYear = date('Y');
//                 $allYears = collect(range($currentYear - 5, $currentYear))->sortDesc()->values();
//             }

//             // Months 1-12
//             $months = collect(range(1, 12));

//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'years' => $allYears,
//                     'months' => $months,
//                 ]
//             ]);
//         } catch (\Exception $e) {
//             \Log::error('Error in NetRevenue getFilterOptions: ' . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * Export data to PDF
//      */
//     public function exportPdf(Request $request)
//     {
//         try {
//             $year = $request->input('year');
//             $month = $request->input('month');

//             if (!$year || !$month) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Year and month are required'
//                 ], 422);
//             }

//             // Get data
//             $data = $this->getData($request);
//             $responseData = $data->getData();

//             if (!$responseData->success) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Failed to fetch data for export'
//                 ], 500);
//             }

//             $records = $responseData->data->records;
//             $totals = $responseData->data->totals;
//             $months = $responseData->data->months;
//             $monthNames = $responseData->data->month_names;

//             // Generate HTML for PDF
//             $html = $this->generatePdfHtml($year, $month, $monthNames, $records, $totals, $months);

//             return response()->json([
//                 'success' => true,
//                 'html' => $html,
//                 'title' => "Net_Revenue_Report_{$year}_{$month}"
//             ]);

//         } catch (\Exception $e) {
//             \Log::error('Error in NetRevenue exportPdf: ' . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * Generate PDF HTML
//      */
//     private function generatePdfHtml($year, $month, $monthNames, $records, $totals, $months)
//     {
//         $monthName = $monthNames[$month] ?? $month;
//         $currentDate = date('Y-m-d H:i:s');

//         $html = '<!DOCTYPE html>
//         <html>
//         <head>
//             <meta charset="UTF-8">
//             <title>Net Revenue Report</title>
//             <style>
//                 body {
//                     font-family: DejaVu Sans, sans-serif;
//                     font-size: 10px;
//                     margin: 20px;
//                 }
//                 .header {
//                     text-align: center;
//                     margin-bottom: 20px;
//                 }
//                 .header h1 {
//                     font-size: 18px;
//                     font-weight: bold;
//                     margin: 0;
//                     padding: 0;
//                 }
//                 .header p {
//                     font-size: 11px;
//                     margin: 5px 0;
//                     color: #666;
//                 }
//                 table {
//                     width: 100%;
//                     border-collapse: collapse;
//                     font-size: 9px;
//                 }
//                 table th {
//                     background-color: #2c3e50;
//                     color: white;
//                     font-weight: bold;
//                     padding: 6px 4px;
//                     border: 1px solid #2c3e50;
//                     text-align: center;
//                 }
//                 table td {
//                     padding: 4px;
//                     border: 1px solid #ddd;
//                     text-align: center;
//                 }
//                 table tr:nth-child(even) {
//                     background-color: #f9f9f9;
//                 }
//                 table tr.total-row {
//                     background-color: #e8f4f8;
//                     font-weight: bold;
//                 }
//                 .text-left {
//                     text-align: left;
//                 }
//                 .text-right {
//                     text-align: right;
//                 }
//                 .text-center {
//                     text-align: center;
//                 }
//                 .footer {
//                     text-align: center;
//                     font-size: 9px;
//                     color: #999;
//                     margin-top: 20px;
//                     border-top: 1px solid #ddd;
//                     padding-top: 10px;
//                 }
//                 .page-break {
//                     page-break-after: always;
//                 }
//             </style>
//         </head>
//         <body>
//             <div class="header">
//                 <h1>Net Revenue Report</h1>
//                 <p>Generated on: ' . $currentDate . '</p>
//                 <p>Year: ' . $year . ' | Month: ' . $monthName . '</p>
//             </div>

//             <table>
//                 <thead>
//                     <tr>
//                         <th style="width:15%;">Head-Program-Project-SubProject-Object</th>
//                         <th style="width:10%;">Revenue Code</th>
//                         <th style="width:10%;">Estimate</th>
//                         <th style="width:10%;">Re-Estimate</th>';

//         foreach ($months as $m) {
//             $html .= '<th style="width:' . (60 / count($months)) . '%;">' . ($monthNames[$m] ?? $m) . '</th>';
//         }

//         $html .= '</tr>
//                 </thead>
//                 <tbody>';

//         foreach ($records as $record) {
//             $html .= '<tr>
//                         <td class="text-left">' . 
//                             ($record['head'] ?? '') . '-' . 
//                             ($record['program'] ?? '') . '-' . 
//                             ($record['project'] ?? '') . '-' . 
//                             ($record['sub_project'] ?? '') . '-' . 
//                             ($record['object'] ?? '') . 
//                         '</td>
//                         <td class="text-left">' . ($record['revenue_code_name'] ?? '') . '</td>
//                         <td class="text-right">' . number_format($record['estimate'] ?? 0, 2) . '</td>
//                         <td class="text-right">' . number_format($record['re_estimate'] ?? 0, 2) . '</td>';

//             foreach ($months as $m) {
//                 $html .= '<td class="text-right">' . number_format($record['months'][$m] ?? 0, 2) . '</td>';
//             }

//             $html .= '</tr>';
//         }

//         // Totals row
//         $html .= '<tr class="total-row">
//                     <td class="text-right" colspan="2"><strong>TOTAL</strong></td>
//                     <td class="text-right"><strong>' . number_format($totals['estimate'] ?? 0, 2) . '</strong></td>
//                     <td class="text-right"><strong>' . number_format($totals['re_estimate'] ?? 0, 2) . '</strong></td>';

//         foreach ($months as $m) {
//             $html .= '<td class="text-right"><strong>' . number_format($totals['months'][$m] ?? 0, 2) . '</strong></td>';
//         }

//         $html .= '</tr>
//                 </tbody>
//             </table>

//             <div class="footer">
//                 <p>Page {PAGE_NUM} of {PAGE_COUNT}</p>
//                 <p>Net Revenue Report - ' . $year . ' - ' . $monthName . '</p>
//             </div>
//         </body>
//         </html>';

//         return $html;
//     }

//     /**
//      * Export data to CSV
//      */
//     public function exportCsv(Request $request)
//     {
//         try {
//             $year = $request->input('year');
//             $month = $request->input('month');

//             if (!$year || !$month) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Year and month are required'
//                 ], 422);
//             }

//             // Get data
//             $data = $this->getData($request);
//             $responseData = $data->getData();

//             if (!$responseData->success) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Failed to fetch data for export'
//                 ], 500);
//             }

//             $records = $responseData->data->records;
//             $totals = $responseData->data->totals;
//             $months = $responseData->data->months;
//             $monthNames = $responseData->data->month_names;

//             // Prepare CSV headers
//             $headers = ['Head-Program-Project-SubProject-Object', 'Revenue Code', 'Estimate', 'Re-Estimate'];
//             foreach ($months as $m) {
//                 $headers[] = $monthNames[$m] ?? $m;
//             }

//             $csvRows = [];
//             $csvRows[] = implode(',', $headers);

//             // Add data rows
//             foreach ($records as $record) {
//                 $row = [
//                     ($record['head'] ?? '') . '-' . ($record['program'] ?? '') . '-' . 
//                     ($record['project'] ?? '') . '-' . ($record['sub_project'] ?? '') . '-' . 
//                     ($record['object'] ?? ''),
//                     $record['revenue_code_name'] ?? '',
//                     number_format($record['estimate'] ?? 0, 2),
//                     number_format($record['re_estimate'] ?? 0, 2)
//                 ];

//                 foreach ($months as $m) {
//                     $row[] = number_format($record['months'][$m] ?? 0, 2);
//                 }

//                 $csvRows[] = implode(',', $row);
//             }

//             // Add totals row
//             $totalRow = ['TOTAL', '', number_format($totals['estimate'] ?? 0, 2), number_format($totals['re_estimate'] ?? 0, 2)];
//             foreach ($months as $m) {
//                 $totalRow[] = number_format($totals['months'][$m] ?? 0, 2);
//             }
//             $csvRows[] = implode(',', $totalRow);

//             // Generate CSV
//             $csvContent = implode("\n", $csvRows);

//             return response($csvContent)
//                 ->header('Content-Type', 'text/csv')
//                 ->header('Content-Disposition', "attachment; filename=net_revenue_{$year}_{$month}.csv");

//         } catch (\Exception $e) {
//             \Log::error('Error in NetRevenue exportCsv: ' . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
// }






namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Treasury;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NetRevenueController extends Controller
{
    /**
     * Get Net Revenue Report data
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
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Get all estimates
            $estimates = Estimate::orderBy('head')
                ->orderBy('program')
                ->orderBy('project')
                ->orderBy('sub_project')
                ->orderBy('object')
                ->get();

            $results = [];

            foreach ($estimates as $estimate) {
                $row = [
                    'head' => $estimate->head,
                    'program' => $estimate->program,
                    'project' => $estimate->project,
                    'sub_project' => $estimate->sub_project,
                    'object' => $estimate->object,
                    'revenue_code_name' => $estimate->revenue_code_name,
                    'estimate' => $estimate->estimate,
                    're_estimate' => $estimate->re_estimate,
                    'months' => [],
                    'refund_months' => [],
                    'total_revenue' => 0,
                    'revenue_refund' => 0,
                    'net_revenue' => 0
                ];

                $totalRevenue = 0;
                $totalRefund = 0;

                // Get monthly values from treasury and monthly_fincances
                foreach ($months as $monthNum) {
                    // Get revenue value (dr_cr_code=4000, dr_cr='CR')
                    $value = $this->getMonthlyValue(
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
                    $row['months'][$monthNum] = $value;
                    $totalRevenue += $value;

                    // Get refund value (dr_cr_code=5000, dr_cr='DR')
                    $refundValue = $this->getMonthlyValue(
                        $year,
                        $monthNum,
                        $estimate->head,
                        $estimate->program,
                        $estimate->project,
                        $estimate->sub_project,
                        $estimate->object,
                        5000,
                        'DR'
                    );
                    $row['refund_months'][$monthNum] = $refundValue;
                    $totalRefund += $refundValue;
                }

                $row['total_revenue'] = round($totalRevenue, 2);
                $row['revenue_refund'] = round($totalRefund, 2);
                $row['net_revenue'] = round($totalRevenue - $totalRefund, 2);

                $results[] = $row;
            }

            // Calculate totals
            $totals = $this->calculateTotals($results, $months);

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => $totals,
                    'months' => $months,
                    'month_names' => $monthNames,
                    'filters' => [
                        'year' => $year,
                        'month' => $month
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in NetRevenue getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get monthly value from treasury and monthly_fincances tables
     */
    private function getMonthlyValue($year, $month, $head, $program, $project, $sub_project, $object, $drCrCode, $drCr)
    {
        $total = 0;

        // Get from Treasury table
        $treasuryValue = Treasury::whereYear('created_at', $year)
            ->where('month', $month)
            ->where('head', $head)
            ->where('program', $program)
            ->where('project', $project)
            ->where('sub_project', $sub_project)
            ->where('object', $object)
            ->where('dr_cr_code', $drCrCode)
            ->where('dr_cr', $drCr)
            ->sum('cash_xe');

        $total += $treasuryValue;

        // Get from MonthlyFincance table
        $monthlyValue = MonthlyFincance::whereYear('created_at', $year)
            ->where('month', $month)
            ->where('head', $head)
            ->where('program', $program)
            ->where('project', $project)
            ->where('sub_project', $sub_project)
            ->where('object', $object)
            ->where('dr_cr_code', $drCrCode)
            ->where('dr_cr', $drCr)
            ->sum('cash_xe');

        $total += $monthlyValue;

        return round($total, 2);
    }

    /**
     * Calculate totals for each month
     */
    private function calculateTotals($results, $months)
    {
        $totals = [
            'estimate' => 0,
            're_estimate' => 0,
            'months' => [],
            'refund_months' => [],
            'total_revenue' => 0,
            'revenue_refund' => 0,
            'net_revenue' => 0
        ];

        foreach ($months as $month) {
            $totals['months'][$month] = 0;
            $totals['refund_months'][$month] = 0;
        }

        foreach ($results as $row) {
            $totals['estimate'] += $row['estimate'] ?? 0;
            $totals['re_estimate'] += $row['re_estimate'] ?? 0;
            $totals['total_revenue'] += $row['total_revenue'] ?? 0;
            $totals['revenue_refund'] += $row['revenue_refund'] ?? 0;
            $totals['net_revenue'] += $row['net_revenue'] ?? 0;
            
            foreach ($months as $month) {
                $totals['months'][$month] += $row['months'][$month] ?? 0;
                $totals['refund_months'][$month] += $row['refund_months'][$month] ?? 0;
            }
        }

        // Round values
        $totals['estimate'] = round($totals['estimate'], 2);
        $totals['re_estimate'] = round($totals['re_estimate'], 2);
        $totals['total_revenue'] = round($totals['total_revenue'], 2);
        $totals['revenue_refund'] = round($totals['revenue_refund'], 2);
        $totals['net_revenue'] = round($totals['net_revenue'], 2);
        
        foreach ($totals['months'] as $key => $value) {
            $totals['months'][$key] = round($value, 2);
        }
        foreach ($totals['refund_months'] as $key => $value) {
            $totals['refund_months'][$key] = round($value, 2);
        }

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
            \Log::error('Error in NetRevenue getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

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

            $records = $responseData->data->records;
            $totals = $responseData->data->totals;
            $months = $responseData->data->months;
            $monthNames = $responseData->data->month_names;

            // Prepare CSV headers
            $headers = ['Head-Program-Project-SubProject-Object', 'Revenue Code', 'Estimate', 'Re-Estimate'];
            foreach ($months as $m) {
                $headers[] = $monthNames[$m] ?? $m;
            }
            $headers[] = 'Total Revenue';
            $headers[] = 'Revenue Refund';
            $headers[] = 'Net Revenue';

            $csvRows = [];
            $csvRows[] = implode(',', $headers);

            // Add data rows
            foreach ($records as $record) {
                $row = [
                    ($record['head'] ?? '') . '-' . ($record['program'] ?? '') . '-' . 
                    ($record['project'] ?? '') . '-' . ($record['sub_project'] ?? '') . '-' . 
                    ($record['object'] ?? ''),
                    $record['revenue_code_name'] ?? '',
                    number_format($record['estimate'] ?? 0, 2),
                    number_format($record['re_estimate'] ?? 0, 2)
                ];

                foreach ($months as $m) {
                    $row[] = number_format($record['months'][$m] ?? 0, 2);
                }

                $row[] = number_format($record['total_revenue'] ?? 0, 2);
                $row[] = number_format($record['revenue_refund'] ?? 0, 2);
                $row[] = number_format($record['net_revenue'] ?? 0, 2);

                $csvRows[] = implode(',', $row);
            }

            // Add totals row
            $totalRow = ['TOTAL', '', number_format($totals['estimate'] ?? 0, 2), number_format($totals['re_estimate'] ?? 0, 2)];
            foreach ($months as $m) {
                $totalRow[] = number_format($totals['months'][$m] ?? 0, 2);
            }
            $totalRow[] = number_format($totals['total_revenue'] ?? 0, 2);
            $totalRow[] = number_format($totals['revenue_refund'] ?? 0, 2);
            $totalRow[] = number_format($totals['net_revenue'] ?? 0, 2);
            $csvRows[] = implode(',', $totalRow);

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=net_revenue_{$year}_{$month}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in NetRevenue exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}