<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Treasury;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueMonthlyController extends Controller
{
    /**
     * Get Revenue Monthly Report data
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

            // Get all estimates
            $estimates = Estimate::orderBy('head')
                ->orderBy('program')
                ->orderBy('project')
                ->orderBy('sub_project')
                ->orderBy('object')
                ->get();

            $results = [];

            foreach ($estimates as $estimate) {
                // Get revenue value (dr_cr_code=4000, dr_cr='CR')
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

                // Get refund value (dr_cr_code=5000, dr_cr='DR')
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

                $row = [
                    'head' => $estimate->head,
                    'program' => $estimate->program,
                    'project' => $estimate->project,
                    'sub_project' => $estimate->sub_project,
                    'object' => $estimate->object,
                    'revenue_code_name' => $estimate->revenue_code_name,
                    'estimate' => $estimate->estimate,
                    're_estimate' => $estimate->re_estimate,
                    'revenue_value' => round($revenueValue, 2),
                    'refund_value' => round($refundValue, 2),
                    'net_revenue' => round($revenueValue - $refundValue, 2)
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
            \Log::error('Error in RevenueMonthly getData: ' . $e->getMessage());
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
     * Calculate totals
     */
    private function calculateTotals($results)
    {
        $totals = [
            'estimate' => 0,
            're_estimate' => 0,
            'revenue_value' => 0,
            'refund_value' => 0,
            'net_revenue' => 0
        ];

        foreach ($results as $row) {
            $totals['estimate'] += $row['estimate'] ?? 0;
            $totals['re_estimate'] += $row['re_estimate'] ?? 0;
            $totals['revenue_value'] += $row['revenue_value'] ?? 0;
            $totals['refund_value'] += $row['refund_value'] ?? 0;
            $totals['net_revenue'] += $row['net_revenue'] ?? 0;
        }

        // Round values
        $totals['estimate'] = round($totals['estimate'], 2);
        $totals['re_estimate'] = round($totals['re_estimate'], 2);
        $totals['revenue_value'] = round($totals['revenue_value'], 2);
        $totals['refund_value'] = round($totals['refund_value'], 2);
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
            \Log::error('Error in RevenueMonthly getFilterOptions: ' . $e->getMessage());
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
            $monthName = $responseData->data->month_name;

            // Prepare CSV headers
            $headers = [
                'Head-Program-Project-SubProject-Object',
                'Revenue Code',
                'Estimate',
                'Re-Estimate',
                'Revenue (Rs)',
                'Refund (Rs)',
                'Net Revenue (Rs)'
            ];

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
                    number_format($record['re_estimate'] ?? 0, 2),
                    number_format($record['revenue_value'] ?? 0, 2),
                    number_format($record['refund_value'] ?? 0, 2),
                    number_format($record['net_revenue'] ?? 0, 2)
                ];

                $csvRows[] = implode(',', $row);
            }

            // Add totals row
            $totalRow = [
                'TOTAL',
                '',
                number_format($totals['estimate'] ?? 0, 2),
                number_format($totals['re_estimate'] ?? 0, 2),
                number_format($totals['revenue_value'] ?? 0, 2),
                number_format($totals['refund_value'] ?? 0, 2),
                number_format($totals['net_revenue'] ?? 0, 2)
            ];
            $csvRows[] = implode(',', $totalRow);

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=revenue_monthly_{$year}_{$monthName}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in RevenueMonthly exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}