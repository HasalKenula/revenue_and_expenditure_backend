<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CBGController extends Controller
{
    /**
     * Get all ministries data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $viewType = $request->input('view_type', 'cumulative');
            
            \Log::info('CBG getData called', [
                'year' => $year,
                'month' => $month,
                'view_type' => $viewType
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

            // Determine months to include based on view type
            if ($viewType === 'cumulative') {
                $monthsToInclude = range(1, (int)$month);
            } else {
                $monthsToInclude = [(int)$month];
            }

            // ========== MAIN MINISTRY ROWS (TRNO = 304) ==========
            $mainMinistryRows = [
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 2004, 'subject_name' => 'Law'],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2005, 'subject_name' => 'Medicine'],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 4, 'object' => 2005, 'subject_name' => 'Tourism'],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 9, 'object' => 2005, 'subject_name' => 'Transport'],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 2201, 'subject_name' => 'Development Department']
            ];

            // ========== EDUCATION MINISTRY ROWS (TRNO = 318) ==========
            $educationMinistryRows = [
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2004, 'subject_name' => 'aa'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2004, 'subject_name' => 'bb'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 3, 'object' => 2004, 'subject_name' => 'cc'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 5, 'object' => 2004, 'subject_name' => 'dd'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2005, 'subject_name' => 'ee'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2005, 'subject_name' => 'ff'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 3, 'object' => 2005, 'subject_name' => 'gg'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 4, 'object' => 2005, 'subject_name' => 'hh'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 5, 'object' => 2005, 'subject_name' => 'ii']
            ];

            // ========== ANIMAL MINISTRY ROWS (TRNO = 311) ==========
            $animalMinistryRows = [
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2004, 'subject_name' => 'aa'],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2004, 'subject_name' => 'bb'],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 3, 'object' => 2004, 'subject_name' => 'cc'],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 3, 'object' => 2005, 'subject_name' => 'dd'],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 4, 'object' => 2005, 'subject_name' => 'ee']
            ];

            // ========== AGRICULTURE MINISTRY ROWS (TRNO = 314) ==========
            $agricultureMinistryRows = [
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2004, 'subject_name' => 'aa'],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 4, 'object' => 2004, 'subject_name' => 'bb'],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 5, 'object' => 2004, 'subject_name' => 'cc'],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 6, 'object' => 2004, 'subject_name' => 'dd']
            ];

            // ========== LAND MINISTRY ROWS (TRNO = 308) ==========
            $landMinistryRows = [
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2004, 'subject_name' => 'aa'],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2004, 'subject_name' => 'bb'],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2005, 'subject_name' => 'cc']
            ];

            // ========== MAIN SECRETARY MINISTRY ROWS (TRNO = 320) ==========
            $mainSecretaryRows = [
                ['trno' => 320, 'program' => 3, 'project' => 5, 'sub_project' => 1, 'object' => 2004, 'subject_name' => 'aa']
            ];

            // Process all ministries
            $mainMinistryResults = $this->processRows($mainMinistryRows, $year, $monthsToInclude);
            $educationMinistryResults = $this->processRows($educationMinistryRows, $year, $monthsToInclude);
            $animalMinistryResults = $this->processRows($animalMinistryRows, $year, $monthsToInclude);
            $agricultureMinistryResults = $this->processRows($agricultureMinistryRows, $year, $monthsToInclude);
            $landMinistryResults = $this->processRows($landMinistryRows, $year, $monthsToInclude);
            $mainSecretaryResults = $this->processRows($mainSecretaryRows, $year, $monthsToInclude);

            // Get month names for display
            $monthNames = $this->getMonthNames();
            $monthNamesToShow = [];
            foreach ($monthsToInclude as $monthNum) {
                $monthNamesToShow[$monthNum] = $monthNames[$monthNum];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'main_ministry' => $mainMinistryResults,
                    'education_ministry' => $educationMinistryResults,
                    'animal_ministry' => $animalMinistryResults,
                    'agriculture_ministry' => $agricultureMinistryResults,
                    'land_ministry' => $landMinistryResults,
                    'main_secretary' => $mainSecretaryResults,
                    'months' => $monthsToInclude,
                    'month_names' => $monthNamesToShow,
                    'filters' => [
                        'year' => $year,
                        'month' => $month,
                        'view_type' => $viewType
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in CBG getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Process rows for a specific ministry
     */
    private function processRows($rows, $year, $monthsToInclude)
    {
        $results = [];
        $grandTotalDebit = 0;
        $grandTotalOtherDebit = 0;
        $grandTotalExpenditure = 0;

        foreach ($rows as $row) {
            $cumulativeDebit = 0;
            $cumulativeOtherDebit = 0;

            foreach ($monthsToInclude as $currentMonth) {
                // Get Debit (trno == head)
                $debit = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 1000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                // Get Other Debit (trno != head)
                $otherDebit = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 1000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $cumulativeDebit += $debit;
                $cumulativeOtherDebit += $otherDebit;
            }

            $totalExpenditure = $cumulativeDebit + $cumulativeOtherDebit;

            $results[] = [
                'trno' => $row['trno'],
                'program' => $row['program'],
                'project' => $row['project'],
                'sub_project' => $row['sub_project'],
                'object' => $row['object'],
                'subject_name' => $row['subject_name'],
                'debit' => round($cumulativeDebit, 2),
                'other_debit' => round($cumulativeOtherDebit, 2),
                'total_expenditure' => round($totalExpenditure, 2),
            ];

            $grandTotalDebit += $cumulativeDebit;
            $grandTotalOtherDebit += $cumulativeOtherDebit;
            $grandTotalExpenditure += $totalExpenditure;
        }

        // Add grand total row
        $results[] = [
            'trno' => null,
            'program' => null,
            'project' => null,
            'sub_project' => null,
            'object' => null,
            'subject_name' => 'Total',
            'debit' => round($grandTotalDebit, 2),
            'other_debit' => round($grandTotalOtherDebit, 2),
            'total_expenditure' => round($grandTotalExpenditure, 2),
        ];

        return $results;
    }

    /**
     * Get month names
     */
    private function getMonthNames()
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];
    }

    /**
     * Get filter options (years and months)
     */
    public function getFilterOptions(Request $request)
    {
        try {
            $years = MonthlyFincance::select(DB::raw('YEAR(created_at) as year'))
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear));
            }

            $months = collect(range(1, 12));

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                    'months' => $months,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in CBG getFilterOptions: ' . $e->getMessage());
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
            $viewType = $request->input('view_type', 'cumulative');

            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year and month are required'
                ], 422);
            }

            if ($viewType === 'cumulative') {
                $monthsToInclude = range(1, (int)$month);
            } else {
                $monthsToInclude = [(int)$month];
            }

            $mainMinistryRows = [
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 2004, 'subject_name' => 'Law'],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2005, 'subject_name' => 'Medicine'],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 4, 'object' => 2005, 'subject_name' => 'Tourism'],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 9, 'object' => 2005, 'subject_name' => 'Transport'],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 2201, 'subject_name' => 'Development Department']
            ];

            $educationMinistryRows = [
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2004, 'subject_name' => 'aa'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2004, 'subject_name' => 'bb'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 3, 'object' => 2004, 'subject_name' => 'cc'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 5, 'object' => 2004, 'subject_name' => 'dd'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2005, 'subject_name' => 'ee'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2005, 'subject_name' => 'ff'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 3, 'object' => 2005, 'subject_name' => 'gg'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 4, 'object' => 2005, 'subject_name' => 'hh'],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 5, 'object' => 2005, 'subject_name' => 'ii']
            ];

            $animalMinistryRows = [
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2004, 'subject_name' => 'aa'],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2004, 'subject_name' => 'bb'],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 3, 'object' => 2004, 'subject_name' => 'cc'],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 3, 'object' => 2005, 'subject_name' => 'dd'],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 4, 'object' => 2005, 'subject_name' => 'ee']
            ];

            $agricultureMinistryRows = [
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2004, 'subject_name' => 'aa'],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 4, 'object' => 2004, 'subject_name' => 'bb'],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 5, 'object' => 2004, 'subject_name' => 'cc'],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 6, 'object' => 2004, 'subject_name' => 'dd']
            ];

            $landMinistryRows = [
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2004, 'subject_name' => 'aa'],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 2, 'object' => 2004, 'subject_name' => 'bb'],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 2005, 'subject_name' => 'cc']
            ];

            $mainSecretaryRows = [
                ['trno' => 320, 'program' => 3, 'project' => 5, 'sub_project' => 1, 'object' => 2004, 'subject_name' => 'aa']
            ];

            $exportData = [];

            // Add Main Ministry data
            $mainData = $this->processRowsForExport($mainMinistryRows, $year, $monthsToInclude);
            $exportData[] = ['Table: MAIN MINISTRY'];
            $exportData = array_merge($exportData, $mainData);
            $exportData[] = [];

            // Add Education Ministry data
            $educationData = $this->processRowsForExport($educationMinistryRows, $year, $monthsToInclude);
            $exportData[] = ['Table: EDUCATION MINISTRY'];
            $exportData = array_merge($exportData, $educationData);
            $exportData[] = [];

            // Add Animal Ministry data
            $animalData = $this->processRowsForExport($animalMinistryRows, $year, $monthsToInclude);
            $exportData[] = ['Table: ANIMAL MINISTRY'];
            $exportData = array_merge($exportData, $animalData);
            $exportData[] = [];

            // Add Agriculture Ministry data
            $agricultureData = $this->processRowsForExport($agricultureMinistryRows, $year, $monthsToInclude);
            $exportData[] = ['Table: AGRICULTURE MINISTRY'];
            $exportData = array_merge($exportData, $agricultureData);
            $exportData[] = [];

            // Add Land Ministry data
            $landData = $this->processRowsForExport($landMinistryRows, $year, $monthsToInclude);
            $exportData[] = ['Table: LAND MINISTRY'];
            $exportData = array_merge($exportData, $landData);
            $exportData[] = [];

            // Add Main Secretary data
            $mainSecretaryData = $this->processRowsForExport($mainSecretaryRows, $year, $monthsToInclude);
            $exportData[] = ['Table: MAIN SECRETARY MINISTRY'];
            $exportData = array_merge($exportData, $mainSecretaryData);

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

    private function processRowsForExport($rows, $year, $monthsToInclude)
    {
        $results = [];
        $grandTotalDebit = 0;
        $grandTotalOtherDebit = 0;
        $grandTotalExpenditure = 0;

        foreach ($rows as $row) {
            $cumulativeDebit = 0;
            $cumulativeOtherDebit = 0;

            foreach ($monthsToInclude as $currentMonth) {
                $debit = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 1000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $otherDebit = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 1000)
                    ->where('dr_cr', 'DR')
                    ->sum('cash_xe');

                $cumulativeDebit += $debit;
                $cumulativeOtherDebit += $otherDebit;
            }

            $totalExpenditure = $cumulativeDebit + $cumulativeOtherDebit;

            $results[] = [
                'TR No' => $row['trno'],
                'Program' => $row['program'],
                'Project' => $row['project'],
                'Sub Project' => $row['sub_project'],
                'Object' => $row['object'],
                'Subject Name' => $row['subject_name'],
                'Debit' => round($cumulativeDebit, 2),
                'Other Debit' => round($cumulativeOtherDebit, 2),
                'Total Expenditure' => round($totalExpenditure, 2),
            ];

            $grandTotalDebit += $cumulativeDebit;
            $grandTotalOtherDebit += $cumulativeOtherDebit;
            $grandTotalExpenditure += $totalExpenditure;
        }

        $results[] = [
            'TR No' => 'TOTAL',
            'Program' => '',
            'Project' => '',
            'Sub Project' => '',
            'Object' => '',
            'Subject Name' => '',
            'Debit' => round($grandTotalDebit, 2),
            'Other Debit' => round($grandTotalOtherDebit, 2),
            'Total Expenditure' => round($grandTotalExpenditure, 2),
        ];

        return $results;
    }
}