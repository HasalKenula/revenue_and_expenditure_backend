<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ODSController extends Controller
{
    /**
     * Get Other Department Surcharge data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            
            \Log::info('ODS getData called', [
                'year' => $year,
                'month' => $month
            ]);

            // Validate year and month
            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            if (!$month || $month < 1 || $month > 12) {
                return response()->json([
                    'success' => false,
                    'message' => 'Valid month is required (1-12)'
                ], 422);
            }

            // Get all records for the selected year and month
            // where dr_cr_code = 2000, dr_cr = CR, and trno != head
            $records = MonthlyFincance::whereYear('created_at', $year)
                ->where('month', $month)
                ->where('dr_cr_code', 2000) // CR code for surcharge
                ->where('dr_cr', 'CR')
                ->whereColumn('trno', '!=', 'head') // trno != head
                ->select(
                    'trno',
                    'head',
                    'program',
                    'project',
                    'object',
                    'sub_project',
                    'funding as sub_object',
                    'cash_xe as surcharge_amount',
                    'subject',
                    'sn',
                    'dr_cr_code',
                    'dr_cr',
                    'month',
                    'year'
                )
                ->orderBy('trno')
                ->orderBy('head')
                ->get();

            if ($records->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No records found for the selected year and month'
                ]);
            }

            // Calculate totals
            $totalSurcharge = $records->sum('surcharge_amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $records,
                    'totals' => [
                        'total_surcharge' => round($totalSurcharge, 2),
                        'total_records' => $records->count()
                    ],
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in ODS getData: ' . $e->getMessage());
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
            // Get available years from created_at where dr_cr_code = 2000 and dr_cr = CR
            $years = MonthlyFincance::where('dr_cr_code', 2000)
                ->where('dr_cr', 'CR')
                ->select(DB::raw('YEAR(created_at) as year'))
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
            \Log::error('Error in ODS getFilterOptions: ' . $e->getMessage());
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

            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
                ], 422);
            }

            // Get data using the same logic
            $records = MonthlyFincance::whereYear('created_at', $year)
                ->where('month', $month)
                ->where('dr_cr_code', 2000)
                ->where('dr_cr', 'CR')
                ->whereColumn('trno', '!=', 'head')
                ->select(
                    'trno',
                    'head',
                    'program',
                    'project',
                    'object',
                    'sub_project',
                    'funding as sub_object',
                    'cash_xe as surcharge_amount',
                    'subject',
                    'sn',
                    'month',
                    'year'
                )
                ->orderBy('trno')
                ->orderBy('head')
                ->get();

            if ($records->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data to export'
                ], 404);
            }

            // Prepare export data
            $exportData = [];
            $totalSurcharge = 0;

            foreach ($records as $record) {
                $exportData[] = [
                    'TR No' => $record->trno,
                    'Head' => $record->head,
                    'Program' => $record->program,
                    'Project' => $record->project,
                    'Object' => $record->object,
                    'Sub Project' => $record->sub_project,
                    'Sub Object' => $record->sub_object,
                    'Subject' => $record->subject,
                    'SN' => $record->sn,
                    'Surcharge Amount' => round($record->surcharge_amount, 2),
                ];
                $totalSurcharge += $record->surcharge_amount;
            }

            // Add totals row
            $exportData[] = [
                'TR No' => 'TOTAL',
                'Head' => '',
                'Program' => '',
                'Project' => '',
                'Object' => '',
                'Sub Project' => '',
                'Sub Object' => '',
                'Subject' => '',
                'SN' => '',
                'Surcharge Amount' => round($totalSurcharge, 2),
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