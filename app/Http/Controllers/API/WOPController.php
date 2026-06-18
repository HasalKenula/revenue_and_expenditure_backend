<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WOPController extends Controller
{
    /**
     * Get WOP data based on year, month, and dr_cr_code
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $drCrCode = 8098; // Fixed code
            
            \Log::info('WOP getData called', [
                'year' => $year,
                'month' => $month,
                'dr_cr_code' => $drCrCode
            ]);

            // Validate year and month
            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
                ], 422);
            }

            // Get distinct TRNOs for the selected year and month
            $trnos = MonthlyFincance::whereYear('created_at', $year)
                ->where('month', $month)
                ->where('dr_cr_code', $drCrCode)
                ->distinct()
                ->pluck('trno')
                ->values();

            if ($trnos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No records found for the selected year and month'
                ]);
            }

            $results = [];

            foreach ($trnos as $trno) {
                // Get DR amount for this TRNO
                $drAmount = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', $drCrCode)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get CR amount for this TRNO
                $crAmount = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', $drCrCode)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Get head/trno details
                $record = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', $drCrCode)
                    ->first();

                $results[] = [
                    'trno' => $trno,
                    'head' => $record ? $record->head : null,
                    'program' => $record ? $record->program : null,
                    'project' => $record ? $record->project : null,
                    'dr_amount' => round($drAmount, 2),
                    'cr_amount' => round($crAmount, 2),
                    'subject' => $record ? $record->subject : null,
                ];
            }

            // Sort by TRNO
            usort($results, function ($a, $b) {
                return $a['trno'] <=> $b['trno'];
            });

            // Calculate totals
            $totalDr = array_sum(array_column($results, 'dr_amount'));
            $totalCr = array_sum(array_column($results, 'cr_amount'));

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => [
                        'total_dr' => round($totalDr, 2),
                        'total_cr' => round($totalCr, 2),
                    ],
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                        'dr_cr_code' => $drCrCode
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in WOP getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get filter options (years and months)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from created_at
            $years = MonthlyFincance::select(DB::raw('YEAR(created_at) as year'))
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            // If no years found, provide default range
            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear));
            }

            // Months 1-12
            $months = collect(range(1, 12));

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                    'months' => $months,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in WOP getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV
     */
    public function export(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $drCrCode = 8098;

            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
                ], 422);
            }

            // Get data using the same logic as getData
            $trnos = MonthlyFincance::whereYear('created_at', $year)
                ->where('month', $month)
                ->where('dr_cr_code', $drCrCode)
                ->distinct()
                ->pluck('trno')
                ->values();

            $exportData = [];

            foreach ($trnos as $trno) {
                $drAmount = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', $drCrCode)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $crAmount = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $month)
                    ->where('trno', $trno)
                    ->where('dr_cr_code', $drCrCode)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $exportData[] = [
                    'TR No' => $trno,
                    'DR Amount' => round($drAmount, 2),
                    'CR Amount' => round($crAmount, 2),
                ];
            }

            // Add totals row
            $totalDr = array_sum(array_column($exportData, 'DR Amount'));
            $totalCr = array_sum(array_column($exportData, 'CR Amount'));
            $exportData[] = [
                'TR No' => 'TOTAL',
                'DR Amount' => round($totalDr, 2),
                'CR Amount' => round($totalCr, 2),
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'total_records' => count($exportData)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}