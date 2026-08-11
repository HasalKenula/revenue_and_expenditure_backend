<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Treasury;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NatureOfRevenueController extends Controller
{
    /**
     * Get Nature of Revenue Report data
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

            // Define revenue categories with their codes in the exact order you want
            $categories = [
                'Taxes on Local Goods and services' => [
                    '1002-12-00', '1002-07-00', '1002-05-04', '1002-04-05',
                    '1002-04-03', '1002-04-04', '1002-04-06', '1002-07-01',
                    '1002-07-02', '1002-09-00'
                ],
                'Licence Fees & others' => [
                    '1003-01-01', '1003-01-02', '1003-03-01', '1003-07-09',
                    '1003-07-10', '1003-07-12'
                ],
                'Revenue on Government Assets' => [
                    '2002-01-03', '2002-01-05', '2002-01-01', '2002-02-02',
                    '2002-02-03'
                ],
                'Sales and Charges' => [
                    '2003-02-22', '2003-02-28', '2003-02-06', '2003-02-29',
                    '2003-02-13', '2003-02-14', '2003-02-21', '2003-02-24',
                    '2003-02-30', '2003-03-01', '2003-03-03', '2003-99-00',
                    '2003-99-01', '2003-99-02', '2003-99-03', '2003-99-04'
                ],
                'Sales of Capital Assets' => [
                    '2006-02-00'
                ]
            ];

            // Get all estimates with revenue code names
            $estimates = Estimate::select('head', 'program', 'project', 'sub_project', 'object', 'revenue_code_name')
                ->orderBy('head')
                ->orderBy('project')
                ->orderBy('object')
                ->get();

            // Create a lookup for estimates by code
            $estimateLookup = [];
            foreach ($estimates as $estimate) {
                $code = $this->formatCombinedCode($estimate);
                $estimateLookup[$code] = $estimate;
            }

            $results = [];
            $grandTotal = 0;
            $categoryTotals = [];
            $groupedResults = [];

            // Process each category in the defined order
            foreach ($categories as $categoryName => $codes) {
                $categoryItems = [];
                $categoryTotal = 0;
                
                // Process each code in the defined order
                foreach ($codes as $code) {
                    // Initialize with zero values
                    $revenueValue = 0;
                    $refundValue = 0;
                    $netRevenue = 0;
                    $revenueCodeName = '';
                    
                    // Check if we have data for this code
                    if (isset($estimateLookup[$code])) {
                        $estimate = $estimateLookup[$code];
                        $revenueCodeName = $estimate->revenue_code_name ?? '';
                        
                        // Get revenue value
                        $revenueValue = $this->getMonthlyValue(
                            $year,
                            $month,
                            $estimate->head,
                            $estimate->program,
                            $estimate->project,
                            $estimate->sub_project,
                            $estimate->object,
                            4000,
                            'CR'
                        );
                        
                        // Get refund value
                        $refundValue = $this->getMonthlyValue(
                            $year,
                            $month,
                            $estimate->head,
                            $estimate->program,
                            $estimate->project,
                            $estimate->sub_project,
                            $estimate->object,
                            5000,
                            'DR'
                        );
                        
                        $netRevenue = $revenueValue - $refundValue;
                    }
                    
                    // Always add the row, even if values are zero
                    $row = [
                        'code' => $code,
                        'revenue_code_name' => $revenueCodeName,
                        'revenue_value' => round($revenueValue, 2),
                        'refund_value' => round($refundValue, 2),
                        'net_revenue' => round($netRevenue, 2),
                        'category' => $categoryName
                    ];
                    
                    $categoryItems[] = $row;
                    $categoryTotal += $netRevenue;
                    $grandTotal += $netRevenue;
                    $results[] = $row;
                }
                
                // Store category with its items in the correct order
                $groupedResults[$categoryName] = [
                    'items' => $categoryItems,
                    'total' => round($categoryTotal, 2)
                ];
                
                $categoryTotals[$categoryName] = round($categoryTotal, 2);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'grouped_records' => $groupedResults,
                    'grand_total' => round($grandTotal, 2),
                    'month' => $month,
                    'year' => $year,
                    'month_name' => $monthNames[$month] ?? $month,
                    'province' => 'Southern'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in NatureOfRevenue getData: ' . $e->getMessage());
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
     * Find category for a code
     */
    private function findCategory($code, $categories)
    {
        foreach ($categories as $categoryName => $codes) {
            if (in_array($code, $codes)) {
                return $categoryName;
            }
        }
        return 'Other';
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
            \Log::error('Error in NatureOfRevenue getFilterOptions: ' . $e->getMessage());
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

            // Convert stdClass objects to arrays
            $groupedRecords = json_decode(json_encode($responseData->data->grouped_records), true);
            $grandTotal = $responseData->data->grand_total ?? 0;
            $monthName = $responseData->data->month_name ?? $month;
            $year = $responseData->data->year ?? $year;

            // Prepare CSV headers with quotes
            $headers = ['Revenue Head', 'Nature of Revenue', 'Amount (RS)'];

            $csvRows = [];
            
            // Add headers with quotes
            $csvRows[] = implode(',', array_map(function($h) { 
                return '"' . $h . '"'; 
            }, $headers));

            // Format number to always show 2 decimal places
            $formatNumber = function($value) {
                if ($value === null || $value === '') return '0.00';
                return number_format((float)$value, 2, '.', '');
            };

            // Format combined code with apostrophe to prevent Excel date conversion
            $formatCombinedCode = function($code) {
                if (empty($code)) return '';
                // Add apostrophe to prevent Excel from converting to date
                return "'" . $code;
            };

            // Add data rows grouped by category - maintaining the defined order
            foreach ($groupedRecords as $category => $categoryData) {
                // Add category header row
                $csvRows[] = ',"' . $category . '",';

                // Check if items exist and is an array
                $items = isset($categoryData['items']) ? $categoryData['items'] : [];
                
                foreach ($items as $item) {
                    // Ensure item is an array
                    if (!is_array($item)) {
                        $item = json_decode(json_encode($item), true);
                    }
                    
                    $row = [
                        '"' . $formatCombinedCode($item['code'] ?? '') . '"',
                        '"' . ($item['revenue_code_name'] ?? '') . '"',
                        $formatNumber($item['net_revenue'] ?? 0)
                    ];
                    $csvRows[] = implode(',', $row);
                }

                // Add category total
                $total = $categoryData['total'] ?? 0;
                $csvRows[] = ',"Total ' . $category . '",' . $formatNumber($total);
                $csvRows[] = ''; // Empty row for spacing
            }

            // Add grand total
            $csvRows[] = ',"Grand Total",' . $formatNumber($grandTotal);

            // Add empty row and summary
            $csvRows[] = '';
            $csvRows[] = '"Report generated on: ' . date('Y-m-d H:i:s') . '"';
            $csvRows[] = '"Period: ' . $monthName . ' ' . $year . '"';

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            // Add BOM for UTF-8 Excel compatibility
            $csvContent = "\xEF\xBB\xBF" . $csvContent;

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=nature_of_revenue_{$year}_{$month}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in NatureOfRevenue exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}