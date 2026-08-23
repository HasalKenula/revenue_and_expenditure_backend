<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RevenueAccountData;
use App\Models\RevenueReceipt;
use App\Models\RevenueOpeningBalance;
use App\Models\AccountNumber;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashInTransactionController extends Controller
{
    /**
     * Default accounts to be pre-selected
     */
    private $defaultAccounts = [
        '013-2-001-6-0011618',
        '014100170003265',
        '71353540',
        '93324673',
        '95609366',
        '013200130055977'
    ];

    /**
     * Helper function to pad number with leading zeros
     */
    private function padNumber($value, $length = 2)
    {
        if ($value === null || $value === '') {
            return str_repeat('0', $length);
        }
        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Format revenue code as head-project-object
     */
    private function formatRevenueCode($estimate)
    {
        if (!$estimate) {
            return '-';
        }

        $head = $this->padNumber($estimate->head, 4);
        $project = $this->padNumber($estimate->project, 2);
        $object = $this->padNumber($estimate->object, 3);
        
        return "{$head}-{$project}-{$object}";
    }

    /**
     * Get Cash IN Transaction Report Data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $selectedAccounts = $request->input('selected_accounts', []);

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            // If no accounts selected, use default accounts
            if (empty($selectedAccounts)) {
                $selectedAccounts = $this->defaultAccounts;
            }

            if (is_string($selectedAccounts)) {
                $selectedAccounts = json_decode($selectedAccounts, true);
                if (!is_array($selectedAccounts)) {
                    $selectedAccounts = [];
                }
            }

            Log::info('CashInTransaction getData called', [
                'year' => $year,
                'selected_accounts' => $selectedAccounts
            ]);

            $months = range(1, 12);
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Get accounts based on selected account numbers
            $accounts = AccountNumber::whereIn('account_number', $selectedAccounts)
                ->orderBy('account_number')
                ->get();

            $results = [];
            $grandTotals = [
                'opening_balance' => 0,
                'revenue_collection' => 0,
                'revenue_receipt' => 0,
                'adjustment' => 0,
                'cash_in_transist' => 0
            ];

            foreach ($accounts as $account) {
                // Get opening balance for the selected year from previous year
                // SUM all opening balances for this account across all revenue codes (estimates)
                $previousYear = $year - 1;
                $openingBalanceAmount = RevenueOpeningBalance::where('account_number_id', $account->id)
                    ->where('year', $previousYear)
                    ->sum('amount') ?? 0;

                // Initialize monthly data
                $monthlyCollection = array_fill(1, 12, 0);
                $monthlyReceipts = array_fill(1, 12, 0);
                $monthlyTransists = array_fill(1, 12, 0);

                // Get revenue collection and receipts for each month
                // SUM across all revenue codes (estimates) for this account
                foreach ($months as $monthNum) {
                    $monthName = $monthNames[$monthNum];
                    
                    // Revenue Collection from revenue_account_data - SUM across all estimates
                    $monthlyCollection[$monthNum] = RevenueAccountData::where('account_number_id', $account->id)
                        ->where('month', $monthName)
                        ->where('year', $year)
                        ->sum('amount') ?? 0;

                    // Revenue Receipt from revenue_receipts - SUM across all estimates
                    $monthlyReceipts[$monthNum] = RevenueReceipt::where('account_number_id', $account->id)
                        ->where('month', $monthName)
                        ->where('year', $year)
                        ->sum('amount') ?? 0;
                }

                // Calculate monthly transists and running balance
                $runningBalance = $openingBalanceAmount;
                $totalTransists = 0;
                
                foreach ($months as $monthNum) {
                    $collection = $monthlyCollection[$monthNum] ?? 0;
                    $receipts = $monthlyReceipts[$monthNum] ?? 0;
                    
                    // Transists = Opening Balance + Revenue Collection - Revenue Receipt
                    $transists = $runningBalance + $collection - $receipts;
                    $monthlyTransists[$monthNum] = $transists;
                    $totalTransists += $transists;
                    
                    // Next month's opening balance is current month's transists
                    $runningBalance = $transists;
                }

                // Total Revenue Collection for all months
                $totalRevenueCollection = array_sum($monthlyCollection);
                
                // Total Revenue Receipt for all months
                $totalRevenueReceipt = array_sum($monthlyReceipts);

                // Final Cash IN Transist (December's closing balance)
                $cashInTransist = $runningBalance;

                // Only include accounts with any data
                if ($openingBalanceAmount != 0 || $totalRevenueCollection != 0 || $totalRevenueReceipt != 0) {
                    $results[] = [
                        'account_number_id' => $account->id,
                        'account_number' => $account->account_number,
                        'account_name' => $account->description ?? '-',
                        'opening_balance' => $openingBalanceAmount,
                        'revenue_collection' => $totalRevenueCollection,
                        'revenue_receipt' => $totalRevenueReceipt,
                        'adjustment' => $totalTransists,
                        'cash_in_transist' => $cashInTransist
                    ];

                    $grandTotals['opening_balance'] += $openingBalanceAmount;
                    $grandTotals['revenue_collection'] += $totalRevenueCollection;
                    $grandTotals['revenue_receipt'] += $totalRevenueReceipt;
                    $grandTotals['adjustment'] += $totalTransists;
                    $grandTotals['cash_in_transist'] += $cashInTransist;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $results,
                    'grand_totals' => $grandTotals,
                    'year' => $year,
                    'selected_accounts' => $selectedAccounts,
                    'total_records' => count($results)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CashInTransaction getData: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get filter options endpoint
     */
    public function getFilterOptions(Request $request)
    {
        try {
            // Get available years from revenue_account_data year column
            $years = RevenueAccountData::select('year')
                ->whereNotNull('year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->values();

            // If no years found, provide default range
            if ($years->isEmpty()) {
                $currentYear = date('Y');
                $years = collect(range($currentYear - 5, $currentYear))->sortDesc()->values();
            }

            // Get all accounts
            $accounts = AccountNumber::orderBy('account_number')
                ->pluck('account_number')
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                    'accounts' => $accounts
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in CashInTransaction getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [
                    'years' => [],
                    'accounts' => []
                ]
            ]);
        }
    }

    /**
     * Export data to CSV
     */
    public function export(Request $request)
    {
        try {
            $year = $request->input('year');
            $selectedAccounts = $request->input('selected_accounts', []);

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            if (empty($selectedAccounts)) {
                $selectedAccounts = $this->defaultAccounts;
            }

            if (is_string($selectedAccounts)) {
                $selectedAccounts = json_decode($selectedAccounts, true);
                if (!is_array($selectedAccounts)) {
                    $selectedAccounts = [];
                }
            }

            // Get data using the same logic
            $dataResponse = $this->getData($request);
            $data = json_decode($dataResponse->getContent(), true);

            if (!$data['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data for export'
                ], 500);
            }

            $records = $data['data']['records'];
            $grandTotals = $data['data']['grand_totals'];

            // Build CSV content
            $csvContent = '';
            
            // Add headers
            $headers = [
                'Account Number',
                'Account Name',
                'Opening Balance (Jan)',
                'Revenue Collection (Total)',
                'Revenue Receipt (Total)',
                'Adjustment (Total Transists)',
                'Cash IN Transist (Dec)'
            ];
            $csvContent .= implode(',', $headers) . "\n";

            // Add data rows
            foreach ($records as $record) {
                $row = [
                    $record['account_number'],
                    $record['account_name'],
                    round($record['opening_balance'], 2),
                    round($record['revenue_collection'], 2),
                    round($record['revenue_receipt'], 2),
                    round($record['adjustment'], 2),
                    round($record['cash_in_transist'], 2)
                ];
                $csvContent .= implode(',', $row) . "\n";
            }

            // Add grand total row
            $grandTotalRow = [
                'GRAND TOTAL',
                '',
                round($grandTotals['opening_balance'], 2),
                round($grandTotals['revenue_collection'], 2),
                round($grandTotals['revenue_receipt'], 2),
                round($grandTotals['adjustment'], 2),
                round($grandTotals['cash_in_transist'], 2)
            ];
            $csvContent .= implode(',', $grandTotalRow) . "\n";

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=cash_in_transaction_{$year}.csv")
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Error in CashInTransaction export: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to PDF
     */
    public function exportPDF(Request $request)
    {
        try {
            $year = $request->input('year');
            $selectedAccounts = $request->input('selected_accounts', []);

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year is required'
                ], 422);
            }

            // Get data using the same logic
            $dataResponse = $this->getData($request);
            $data = json_decode($dataResponse->getContent(), true);

            if (!$data['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data for export'
                ], 500);
            }

            // Return data for frontend PDF generation
            return response()->json([
                'success' => true,
                'data' => $data['data'],
                'year' => $year
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CashInTransaction exportPDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
