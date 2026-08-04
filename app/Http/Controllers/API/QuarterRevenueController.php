<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Treasury;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuarterRevenueController extends Controller
{
    /**
     * Get Quarter Revenue Report data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $quarter = $request->input('quarter');

            if (!$year || !$quarter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and quarter are required'
                ], 422);
            }

            // Get months for the selected quarter
            $quarterMonths = $this->getQuarterMonths($quarter);
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
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
                    'total_quarter_revenue' => 0,
                    'quarter_refund' => 0,
                    'net_quarter_revenue' => 0
                ];

                $totalRevenue = 0;
                $totalRefund = 0;

                // Get monthly values for each month in the quarter
                foreach ($quarterMonths as $monthNum) {
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

                $row['total_quarter_revenue'] = round($totalRevenue, 2);
                $row['quarter_refund'] = round($totalRefund, 2);
                $row['net_quarter_revenue'] = round($totalRevenue - $totalRefund, 2);

                $results[] = $row;
            }

            // Calculate totals
            $totals = $this->calculateTotals($results, $quarterMonths);

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => $totals,
                    'months' => $quarterMonths,
                    'month_names' => $monthNames,
                    'quarter' => $quarter,
                    'quarter_label' => $this->getQuarterLabel($quarter),
                    'filters' => [
                        'year' => $year,
                        'quarter' => $quarter
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in QuarterRevenue getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get months for a given quarter
     */
    private function getQuarterMonths($quarter)
    {
        switch ($quarter) {
            case 1:
                return [1, 2, 3];
            case 2:
                return [4, 5, 6];
            case 3:
                return [7, 8, 9];
            case 4:
                return [10, 11, 12];
            default:
                return [];
        }
    }

    /**
     * Get quarter label
     */
    private function getQuarterLabel($quarter)
    {
        switch ($quarter) {
            case 1:
                return 'Q1 (Jan - Mar)';
            case 2:
                return 'Q2 (Apr - Jun)';
            case 3:
                return 'Q3 (Jul - Sep)';
            case 4:
                return 'Q4 (Oct - Dec)';
            default:
                return '';
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
     * Calculate totals for the quarter
     */
    private function calculateTotals($results, $months)
    {
        $totals = [
            'estimate' => 0,
            're_estimate' => 0,
            'months' => [],
            'refund_months' => [],
            'total_quarter_revenue' => 0,
            'quarter_refund' => 0,
            'net_quarter_revenue' => 0
        ];

        foreach ($months as $month) {
            $totals['months'][$month] = 0;
            $totals['refund_months'][$month] = 0;
        }

        foreach ($results as $row) {
            $totals['estimate'] += $row['estimate'] ?? 0;
            $totals['re_estimate'] += $row['re_estimate'] ?? 0;
            $totals['total_quarter_revenue'] += $row['total_quarter_revenue'] ?? 0;
            $totals['quarter_refund'] += $row['quarter_refund'] ?? 0;
            $totals['net_quarter_revenue'] += $row['net_quarter_revenue'] ?? 0;
            
            foreach ($months as $month) {
                $totals['months'][$month] += $row['months'][$month] ?? 0;
                $totals['refund_months'][$month] += $row['refund_months'][$month] ?? 0;
            }
        }

        // Round values
        $totals['estimate'] = round($totals['estimate'], 2);
        $totals['re_estimate'] = round($totals['re_estimate'], 2);
        $totals['total_quarter_revenue'] = round($totals['total_quarter_revenue'], 2);
        $totals['quarter_refund'] = round($totals['quarter_refund'], 2);
        $totals['net_quarter_revenue'] = round($totals['net_quarter_revenue'], 2);
        
        foreach ($totals['months'] as $key => $value) {
            $totals['months'][$key] = round($value, 2);
        }
        foreach ($totals['refund_months'] as $key => $value) {
            $totals['refund_months'][$key] = round($value, 2);
        }

        return $totals;
    }

    /**
     * Get filter options (years and quarters)
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

            // Quarters 1-4
            $quarters = collect(range(1, 4));

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $allYears,
                    'quarters' => $quarters,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in QuarterRevenue getFilterOptions: ' . $e->getMessage());
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
            $quarter = $request->input('quarter');

            if (!$year || !$quarter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and quarter are required'
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
            $months = json_decode(json_encode($responseData->data->months), true);
            $monthNames = json_decode(json_encode($responseData->data->month_names), true);
            $quarterLabel = $responseData->data->quarter_label ?? '';

            // Prepare CSV headers
            $headers = ['Revenue Code', 'Revenue Category', 'Estimate', 'Re-Estimate'];
            foreach ($months as $m) {
                $headers[] = $monthNames[$m] ?? "Month $m";
            }
            $headers[] = 'Total Quarter Revenue';
            $headers[] = 'Quarter Refund';
            $headers[] = 'Net Quarter Revenue';

            $csvRows = [];
            
            // Add headers with quotes
            $csvRows[] = implode(',', array_map(function($h) { 
                return '"' . $h . '"'; 
            }, $headers));

            // Format number without commas
            $formatNumber = function($value) {
                if ($value === null || $value === '') return '0.00';
                return number_format((float)$value, 2, '.', '');
            };

            // Format combined code with proper formatting and prevent Excel date conversion
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
                    $formatNumber($record['estimate'] ?? 0),
                    $formatNumber($record['re_estimate'] ?? 0)
                ];

                foreach ($months as $m) {
                    $row[] = $formatNumber($record['months'][$m] ?? 0);
                }

                $row[] = $formatNumber($record['total_quarter_revenue'] ?? 0);
                $row[] = $formatNumber($record['quarter_refund'] ?? 0);
                $row[] = $formatNumber($record['net_quarter_revenue'] ?? 0);

                $csvRows[] = implode(',', $row);
            }

            // Add totals row
            $totalRow = ['"TOTAL"', '', $formatNumber($totals['estimate'] ?? 0), $formatNumber($totals['re_estimate'] ?? 0)];
            foreach ($months as $m) {
                $totalRow[] = $formatNumber($totals['months'][$m] ?? 0);
            }
            $totalRow[] = $formatNumber($totals['total_quarter_revenue'] ?? 0);
            $totalRow[] = $formatNumber($totals['quarter_refund'] ?? 0);
            $totalRow[] = $formatNumber($totals['net_quarter_revenue'] ?? 0);
            $csvRows[] = implode(',', $totalRow);

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            // Add BOM for UTF-8 Excel compatibility
            $csvContent = "\xEF\xBB\xBF" . $csvContent;

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=quarter_revenue_{$year}_Q{$quarter}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in QuarterRevenue exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}