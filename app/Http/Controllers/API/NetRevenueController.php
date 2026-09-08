<?php

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
     * Export data to CSV - Fixed version with proper text formatting
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

            // Get months from January to selected month
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
            $totals = [
                'estimate' => 0,
                're_estimate' => 0,
                'months' => [],
                'total_revenue' => 0,
                'revenue_refund' => 0,
                'net_revenue' => 0
            ];

            // Initialize month totals
            foreach ($months as $m) {
                $totals['months'][$m] = 0;
            }

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
                    'total_revenue' => 0,
                    'revenue_refund' => 0,
                    'net_revenue' => 0
                ];

                $totalRevenue = 0;
                $totalRefund = 0;

                foreach ($months as $monthNum) {
                    $value = $this->getMonthlyValue(
                        $year, $monthNum,
                        $estimate->head, $estimate->program,
                        $estimate->project, $estimate->sub_project,
                        $estimate->object, 4000, 'CR'
                    );
                    $row['months'][$monthNum] = $value;
                    $totalRevenue += $value;

                    $refundValue = $this->getMonthlyValue(
                        $year, $monthNum,
                        $estimate->head, $estimate->program,
                        $estimate->project, $estimate->sub_project,
                        $estimate->object, 5000, 'DR'
                    );
                    $row['refund_months'][$monthNum] = $refundValue;
                    $totalRefund += $refundValue;
                }

                $row['total_revenue'] = round($totalRevenue, 2);
                $row['revenue_refund'] = round($totalRefund, 2);
                $row['net_revenue'] = round($totalRevenue - $totalRefund, 2);

                $results[] = $row;

                $totals['estimate'] += $estimate->estimate ?? 0;
                $totals['re_estimate'] += $estimate->re_estimate ?? 0;
                $totals['total_revenue'] += $row['total_revenue'];
                $totals['revenue_refund'] += $row['revenue_refund'];
                $totals['net_revenue'] += $row['net_revenue'];
                
                foreach ($months as $m) {
                    $totals['months'][$m] += $row['months'][$m] ?? 0;
                }
            }

            // Round totals
            $totals['estimate'] = round($totals['estimate'], 2);
            $totals['re_estimate'] = round($totals['re_estimate'], 2);
            $totals['total_revenue'] = round($totals['total_revenue'], 2);
            $totals['revenue_refund'] = round($totals['revenue_refund'], 2);
            $totals['net_revenue'] = round($totals['net_revenue'], 2);
            foreach ($totals['months'] as $key => $value) {
                $totals['months'][$key] = round($value, 2);
            }

            // Prepare CSV headers
            $headers = ['Revenue Code', 'Revenue Category', 'Original Estimate', 'Revised Estimate'];
            foreach ($months as $m) {
                $headers[] = $monthNames[$m] ?? $m;
            }
            $headers[] = 'Total Revenue';
            $headers[] = 'Revenue Refund';
            $headers[] = 'Net Revenue';

            $csvRows = [];
            
            // Add headers
            $csvRows[] = implode(',', array_map(function($h) { 
                return '"' . $h . '"'; 
            }, $headers));

            // Format combined code - FORCE TEXT with apostrophe
            $formatCombinedCode = function($record) {
                $head = (string)($record['head'] ?? '');
                $project = (string)($record['project'] ?? '');
                $object = (string)($record['object'] ?? '');
                
                // Format function for 2-digit parts
                $formatPart = function($value) {
                    if ($value === '' || $value === null) {
                        return '00';
                    }
                    $value = trim($value);
                    if (is_numeric($value)) {
                        return str_pad($value, 2, '0', STR_PAD_LEFT);
                    }
                    return $value;
                };
                
                $formattedHead = $head ?: '0';
                $formattedProject = $formatPart($project);
                $formattedObject = $formatPart($object);
                
                $code = "{$formattedHead}-{$formattedProject}-{$formattedObject}";
                
                // CRITICAL FIX: Add apostrophe to force Excel to treat as text
                // This prevents Excel from auto-converting to dates
                return " " . $code;
            };

            // Format number without commas
            $formatNumber = function($value) {
                if ($value === null || $value === '') return '0.00';
                return number_format((float)$value, 2, '.', '');
            };

            // Add data rows
            foreach ($results as $record) {
                $row = [
                    '"' . $formatCombinedCode($record) . '"',
                    '"' . ($record['revenue_code_name'] ?? '') . '"',
                    $formatNumber($record['estimate'] ?? 0),
                    $formatNumber($record['re_estimate'] ?? 0)
                ];

                foreach ($months as $m) {
                    $row[] = $formatNumber($record['months'][$m] ?? 0);
                }

                $row[] = $formatNumber($record['total_revenue'] ?? 0);
                $row[] = $formatNumber($record['revenue_refund'] ?? 0);
                $row[] = $formatNumber($record['net_revenue'] ?? 0);

                $csvRows[] = implode(',', $row);
            }

            // Add totals row
            $totalRow = ['"TOTAL"', '', $formatNumber($totals['estimate'] ?? 0), $formatNumber($totals['re_estimate'] ?? 0)];
            foreach ($months as $m) {
                $totalRow[] = $formatNumber($totals['months'][$m] ?? 0);
            }
            $totalRow[] = $formatNumber($totals['total_revenue'] ?? 0);
            $totalRow[] = $formatNumber($totals['revenue_refund'] ?? 0);
            $totalRow[] = $formatNumber($totals['net_revenue'] ?? 0);
            $csvRows[] = implode(',', $totalRow);

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            // Add BOM for UTF-8 Excel compatibility
            $csvContent = "\xEF\xBB\xBF" . $csvContent;

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
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