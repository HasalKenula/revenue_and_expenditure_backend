<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Treasury;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueCollectionAccountController extends Controller
{
    /**
     * Get Revenue Collection Account Report data
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

            // Get all unique combinations of head, program, project, sub_project, object
            // from treasury table for the selected year
            $combinations = Treasury::whereYear('created_at', $year)
                ->where('dr_cr_code', 4000)
                ->where('dr_cr', 'CR')
                ->select(
                    'head',
                    'program',
                    'project',
                    'sub_project',
                    'object'
                )
                ->distinct()
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
                $row = [
                    'head' => $combo->head,
                    'program' => $combo->program,
                    'project' => $combo->project,
                    'sub_project' => $combo->sub_project,
                    'object' => $combo->object,
                    'months' => [],
                    'total' => 0
                ];

                $totalAmount = 0;

                // Get monthly values for all 12 months
                for ($month = 1; $month <= 12; $month++) {
                    $value = Treasury::whereYear('created_at', $year)
                        ->where('month', $month)
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

            // Calculate totals for each month
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

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'totals' => $totals,
                    'month_names' => $monthNames,
                    'filters' => [
                        'year' => $year
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in RevenueCollectionAccount getData: ' . $e->getMessage());
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
            // Get available years from created_at in Treasury table
            $years = Treasury::select(DB::raw('DISTINCT YEAR(created_at) as year'))
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
            \Log::error('Error in RevenueCollectionAccount getFilterOptions: ' . $e->getMessage());
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

            $records = $responseData->data->records;
            $totals = $responseData->data->totals;
            $monthNames = $responseData->data->month_names;

            // Prepare CSV headers
            $headers = ['Revenue Code', 'Revenue Category', 'Head-Program-Project-SubProject-Object'];
            for ($i = 1; $i <= 12; $i++) {
                $headers[] = $monthNames[$i];
            }
            $headers[] = 'Total';

            $csvRows = [];
            $csvRows[] = implode(',', $headers);

            // Add data rows
            foreach ($records as $record) {
                $row = [
                    $record['revenue_code_name'] ?? '',
                    '',
                    ($record['head'] ?? '') . '-' . ($record['program'] ?? '') . '-' .
                    ($record['project'] ?? '') . '-' . ($record['sub_project'] ?? '') . '-' .
                    ($record['object'] ?? '')
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $row[] = number_format($record['months'][$i] ?? 0, 2);
                }

                $row[] = number_format($record['total'] ?? 0, 2);

                $csvRows[] = implode(',', $row);
            }

            // Add totals row
            $totalRow = ['TOTAL', '', ''];
            for ($i = 1; $i <= 12; $i++) {
                $totalRow[] = number_format($totals['months'][$i] ?? 0, 2);
            }
            $totalRow[] = number_format($totals['total'] ?? 0, 2);
            $csvRows[] = implode(',', $totalRow);

            // Generate CSV
            $csvContent = implode("\n", $csvRows);

            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=revenue_collection_account_{$year}.csv");

        } catch (\Exception $e) {
            \Log::error('Error in RevenueCollectionAccount exportCsv: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}