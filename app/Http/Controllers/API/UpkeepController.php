<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFincance;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpkeepController extends Controller
{
    /**
     * Get Upkeep (All categories) Report data
     */
    public function getData(Request $request)
    {
        try {
            $year = $request->input('year');
            $month = $request->input('month');
            $viewType = $request->input('view_type', 'cumulative');
            
            \Log::info('Upkeep getData called', [
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

            // ========== EDUCATION (UPKEEP) ROWS (TRNO = 310) ==========
            $educationRows = [
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1305],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1305]
            ];

            // ========== WESTERN MEDICINE ROWS (TRNO = 305) ==========
            $westernMedicineRows = [
                ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1304]
            ];

            // ========== INDIGENOUS MEDICINE ROWS (TRNO = 307) ==========
            $indigenousMedicineRows = [
                ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1303]
            ];

            // ========== ROADS & IRRIGATION ROWS (TRNO = 308 & 316) ==========
            $roadsIrrigationRows = [
                ['trno' => 308, 'program' => 50, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1304]
            ];

            // ========== AGRICULTURE ROWS (TRNO = 315) ==========
            $agricultureRows = [
                ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1304]
            ];

            // ========== PROBATION & CHILDCARE ROWS (TRNO = 319) ==========
            $probationChildcareRows = [
                ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1303]
            ];

            // ========== SOCIAL SERVICES ROWS (TRNO = 306) ==========
            $socialServicesRows = [
                ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1304]
            ];

            // ========== LOCAL GOVERNMENT ROWS (TRNO = 312) ==========
            $localGovernmentRows = [
                ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303]
            ];

            // ========== LIVESTOCK ROWS (TRNO = 300-325 excluding others) ==========
            $livestockRows = [
                // TRNO 300
                ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 301
                ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 302
                ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 1304],
                // TRNO 303
                ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 304
                ['trno' => 304, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 304, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                // TRNO 306 (Social Services already defined above, but also has livestock)
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 308 (Roads & Irrigation already defined above, but also has livestock)
                ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 1304],
                ['trno' => 308, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 308, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                // TRNO 309
                ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
                // TRNO 311
                ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 313
                ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 314
                ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 317
                ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 317, 'program' => 53, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                // TRNO 318
                ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 320
                ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 320, 'program' => 3, 'project' => 4, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 320, 'program' => 3, 'project' => 4, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 320, 'program' => 3, 'project' => 5, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 320, 'program' => 3, 'project' => 5, 'sub_project' => 0, 'object' => 1302],
                // TRNO 321
                ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 322
                ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                // TRNO 323
                ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 324
                ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                // TRNO 325
                ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303]
            ];

            // Get subject names from budgets table
            $educationRows = $this->addSubjectNames($educationRows);
            $westernMedicineRows = $this->addSubjectNames($westernMedicineRows);
            $indigenousMedicineRows = $this->addSubjectNames($indigenousMedicineRows);
            $roadsIrrigationRows = $this->addSubjectNames($roadsIrrigationRows);
            $agricultureRows = $this->addSubjectNames($agricultureRows);
            $probationChildcareRows = $this->addSubjectNames($probationChildcareRows);
            $socialServicesRows = $this->addSubjectNames($socialServicesRows);
            $localGovernmentRows = $this->addSubjectNames($localGovernmentRows);
            $livestockRows = $this->addSubjectNames($livestockRows);

            // Process the data
            $educationResults = $this->processRows($educationRows, $year, $monthsToInclude);
            $westernMedicineResults = $this->processRows($westernMedicineRows, $year, $monthsToInclude);
            $indigenousMedicineResults = $this->processRows($indigenousMedicineRows, $year, $monthsToInclude);
            $roadsIrrigationResults = $this->processRows($roadsIrrigationRows, $year, $monthsToInclude);
            $agricultureResults = $this->processRows($agricultureRows, $year, $monthsToInclude);
            $probationChildcareResults = $this->processRows($probationChildcareRows, $year, $monthsToInclude);
            $socialServicesResults = $this->processRows($socialServicesRows, $year, $monthsToInclude);
            $localGovernmentResults = $this->processRows($localGovernmentRows, $year, $monthsToInclude);
            $livestockResults = $this->processRows($livestockRows, $year, $monthsToInclude);

            // Get month names for display
            $monthNames = $this->getMonthNames();
            $monthNamesToShow = [];
            foreach ($monthsToInclude as $monthNum) {
                $monthNamesToShow[$monthNum] = $monthNames[$monthNum];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'education' => $educationResults,
                    'western_medicine' => $westernMedicineResults,
                    'indigenous_medicine' => $indigenousMedicineResults,
                    'roads_irrigation' => $roadsIrrigationResults,
                    'agriculture' => $agricultureResults,
                    'probation_childcare' => $probationChildcareResults,
                    'social_services' => $socialServicesResults,
                    'local_government' => $localGovernmentResults,
                    'livestock' => $livestockResults,
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
            \Log::error('Error in Upkeep getData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Add subject names from budgets table to rows
     */
    private function addSubjectNames($rows)
    {
        foreach ($rows as &$row) {
            // Query the budgets table to get objname (subject name)
            $budget = Budget::where('head', $row['trno'])
                ->where('program', $row['program'])
                ->where('project', $row['project'])
                ->where('subproj', $row['sub_project'])
                ->where('object', $row['object'])
                ->first();

            // Get subject name from budget, fallback to default if not found
            $row['subject_name'] = $budget ? $budget->objname : 'Unknown';
        }

        return $rows;
    }

    /**
     * Process rows for a specific category
     */
    private function processRows($rows, $year, $monthsToInclude)
    {
        $results = [];
        $grandTotalAllocation = 0;
        $grandTotalExpenditure = 0;
        $grandTotalBalance = 0;

        foreach ($rows as $row) {
            // Get Allocation from Budget table
            $allocation = Budget::where('head', $row['trno'])
                ->where('program', $row['program'])
                ->where('project', $row['project'])
                ->where('subproj', $row['sub_project'])
                ->where('object', $row['object'])
                ->sum('amount');

            $cumulativeExpenditure = 0;

            foreach ($monthsToInclude as $currentMonth) {
                // ========== DEBIT (trno == head) ==========
                // Get DR amount (code 1000)
                $debitDR = MonthlyFincance::whereYear('created_at', $year)
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

                // Get CR amount (code 2000)
                $debitCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 2000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Net Debit = DR - CR
                $netDebit = $debitDR - $debitCR;

                // ========== OTHER DEBIT (trno != head) ==========
                // Get DR amount (code 1000)
                $otherDebitDR = MonthlyFincance::whereYear('created_at', $year)
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

                // Get CR amount (code 2000)
                $otherDebitCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 2000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                // Net Other Debit = DR - CR
                $netOtherDebit = $otherDebitDR - $otherDebitCR;

                // Total expenditure for this month = Debit + Other Debit
                $monthExpenditure = $netDebit + $netOtherDebit;
                $cumulativeExpenditure += $monthExpenditure;
            }

            $balance = $allocation - $cumulativeExpenditure;

            $results[] = [
                'trno' => $row['trno'],
                'program' => $row['program'],
                'project' => $row['project'],
                'sub_project' => $row['sub_project'],
                'object' => $row['object'],
                'subject_name' => $row['subject_name'],
                'allocation' => round($allocation, 2),
                'expenditure' => round($cumulativeExpenditure, 2),
                'balance' => round($balance, 2),
            ];

            $grandTotalAllocation += $allocation;
            $grandTotalExpenditure += $cumulativeExpenditure;
            $grandTotalBalance += $balance;
        }

        // Add grand total row
        $results[] = [
            'trno' => null,
            'program' => null,
            'project' => null,
            'sub_project' => null,
            'object' => null,
            'subject_name' => 'Total',
            'allocation' => round($grandTotalAllocation, 2),
            'expenditure' => round($grandTotalExpenditure, 2),
            'balance' => round($grandTotalBalance, 2),
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
            // Get available years from created_at timestamp
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
            \Log::error('Error in Upkeep getFilterOptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV
     */
   /**
     * Export data to CSV
     */
    // public function export(Request $request)
    // {
    //     try {
    //         $year = $request->input('year');
    //         $month = $request->input('month');
    //         $viewType = $request->input('view_type', 'cumulative');

    //         if (!$year || !$month) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Year and month are required'
    //             ], 422);
    //         }

    //         if ($viewType === 'cumulative') {
    //             $monthsToInclude = range(1, (int)$month);
    //         } else {
    //             $monthsToInclude = [(int)$month];
    //         }

    //         // Define all rows (same as above)
    //         $educationRows = [
    //             ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1305],
    //             ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
    //             ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1305]
    //         ];

    //         $westernMedicineRows = [
    //             ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1304]
    //         ];

    //         $indigenousMedicineRows = [
    //             ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1303]
    //         ];

    //         $roadsIrrigationRows = [
    //             ['trno' => 308, 'program' => 50, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
    //             ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1304]
    //         ];

    //         $agricultureRows = [
    //             ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1304]
    //         ];

    //         $probationChildcareRows = [
    //             ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1303]
    //         ];

    //         $socialServicesRows = [
    //             ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1304]
    //         ];

    //         $localGovernmentRows = [
    //             ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303]
    //         ];

    //         $livestockRows = [
    //             // TRNO 300
    //             ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 301
    //             ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 302
    //             ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 1304],
    //             // TRNO 303
    //             ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 304
    //             ['trno' => 304, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 304, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             // TRNO 306 (Social Services already defined above, but also has livestock)
    //             ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 308 (Roads & Irrigation already defined above, but also has livestock)
    //             ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 1304],
    //             ['trno' => 308, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 308, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             // TRNO 309
    //             ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
    //             // TRNO 311
    //             ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 313
    //             ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
    //             ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 314
    //             ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 317
    //             ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 317, 'program' => 53, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             // TRNO 318
    //             ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
    //             ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
    //             ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 320
    //             ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 320, 'program' => 3, 'project' => 4, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 320, 'program' => 3, 'project' => 4, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 320, 'program' => 3, 'project' => 5, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 320, 'program' => 3, 'project' => 5, 'sub_project' => 0, 'object' => 1302],
    //             // TRNO 321
    //             ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 322
    //             ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 323
    //             ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 324
    //             ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
    //             // TRNO 325
    //             ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
    //             ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
    //             ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303]
    //         ];

    //         // Add subject names
    //         $educationRows = $this->addSubjectNames($educationRows);
    //         $westernMedicineRows = $this->addSubjectNames($westernMedicineRows);
    //         $indigenousMedicineRows = $this->addSubjectNames($indigenousMedicineRows);
    //         $roadsIrrigationRows = $this->addSubjectNames($roadsIrrigationRows);
    //         $agricultureRows = $this->addSubjectNames($agricultureRows);
    //         $probationChildcareRows = $this->addSubjectNames($probationChildcareRows);
    //         $socialServicesRows = $this->addSubjectNames($socialServicesRows);
    //         $localGovernmentRows = $this->addSubjectNames($localGovernmentRows);
    //         $livestockRows = $this->addSubjectNames($livestockRows);

    //         $exportData = [];

    //         // Process each category and add to export
    //         $categories = [
    //             'EDUCATION (UPKEEP)' => $educationRows,
    //             'WESTERN MEDICINE' => $westernMedicineRows,
    //             'INDIGENOUS MEDICINE' => $indigenousMedicineRows,
    //             'ROADS & IRRIGATION' => $roadsIrrigationRows,
    //             'AGRICULTURE' => $agricultureRows,
    //             'PROBATION & CHILDCARE' => $probationChildcareRows,
    //             'SOCIAL SERVICES' => $socialServicesRows,
    //             'LOCAL GOVERNMENT' => $localGovernmentRows,
    //             'LIVESTOCK' => $livestockRows
    //         ];

    //         foreach ($categories as $name => $rows) {
    //             $data = $this->processRowsForExport($rows, $year, $monthsToInclude);
    //             $exportData[] = ["Table: $name"];
    //             $exportData = array_merge($exportData, $data);
    //             $exportData[] = [];
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'data' => $exportData,
    //             'total_records' => count($exportData)
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // private function processRowsForExport($rows, $year, $monthsToInclude)
    // {
    //     $results = [];
    //     $grandTotalAllocation = 0;
    //     $grandTotalExpenditure = 0;
    //     $grandTotalBalance = 0;

    //     foreach ($rows as $row) {
    //         $allocation = Budget::where('head', $row['trno'])
    //             ->where('program', $row['program'])
    //             ->where('project', $row['project'])
    //             ->where('subproj', $row['sub_project'])
    //             ->where('object', $row['object'])
    //             ->sum('amount');

    //         $cumulativeExpenditure = 0;

    //         foreach ($monthsToInclude as $currentMonth) {
    //             $debitDR = MonthlyFincance::whereYear('created_at', $year)
    //                 ->where('month', $currentMonth)
    //                 ->where('trno', $row['trno'])
    //                 ->where('head', $row['trno'])
    //                 ->where('program', $row['program'])
    //                 ->where('project', $row['project'])
    //                 ->where('sub_project', $row['sub_project'])
    //                 ->where('object', $row['object'])
    //                 ->where('dr_cr_code', 1000)
    //                 ->where('dr_cr', 'DR')
    //                 ->sum('cash_xe');

    //             $debitCR = MonthlyFincance::whereYear('created_at', $year)
    //                 ->where('month', $currentMonth)
    //                 ->where('trno', $row['trno'])
    //                 ->where('head', $row['trno'])
    //                 ->where('program', $row['program'])
    //                 ->where('project', $row['project'])
    //                 ->where('sub_project', $row['sub_project'])
    //                 ->where('object', $row['object'])
    //                 ->where('dr_cr_code', 2000)
    //                 ->where('dr_cr', 'CR')
    //                 ->sum('cash_xe');

    //             $netDebit = $debitDR - $debitCR;

    //             $otherDebitDR = MonthlyFincance::whereYear('created_at', $year)
    //                 ->where('month', $currentMonth)
    //                 ->where('trno', '!=', $row['trno'])
    //                 ->where('head', $row['trno'])
    //                 ->where('program', $row['program'])
    //                 ->where('project', $row['project'])
    //                 ->where('sub_project', $row['sub_project'])
    //                 ->where('object', $row['object'])
    //                 ->where('dr_cr_code', 1000)
    //                 ->where('dr_cr', 'DR')
    //                 ->sum('cash_xe');

    //             $otherDebitCR = MonthlyFincance::whereYear('created_at', $year)
    //                 ->where('month', $currentMonth)
    //                 ->where('trno', '!=', $row['trno'])
    //                 ->where('head', $row['trno'])
    //                 ->where('program', $row['program'])
    //                 ->where('project', $row['project'])
    //                 ->where('sub_project', $row['sub_project'])
    //                 ->where('object', $row['object'])
    //                 ->where('dr_cr_code', 2000)
    //                 ->where('dr_cr', 'CR')
    //                 ->sum('cash_xe');

    //             $netOtherDebit = $otherDebitDR - $otherDebitCR;
    //             $cumulativeExpenditure += ($netDebit + $netOtherDebit);
    //         }

    //         $balance = $allocation - $cumulativeExpenditure;

    //         $results[] = [
    //             'TR No' => $row['trno'],
    //             'Program' => $row['program'],
    //             'Project' => $row['project'],
    //             'Sub Project' => $row['sub_project'],
    //             'Object' => $row['object'],
    //             'Subject Name' => $row['subject_name'],
    //             'Allocation' => round($allocation, 2),
    //             'Expenditure' => round($cumulativeExpenditure, 2),
    //             'Balance' => round($balance, 2),
    //         ];

    //         $grandTotalAllocation += $allocation;
    //         $grandTotalExpenditure += $cumulativeExpenditure;
    //         $grandTotalBalance += $balance;
    //     }

    //     $results[] = [
    //         'TR No' => 'TOTAL',
    //         'Program' => '',
    //         'Project' => '',
    //         'Sub Project' => '',
    //         'Object' => '',
    //         'Subject Name' => '',
    //         'Allocation' => round($grandTotalAllocation, 2),
    //         'Expenditure' => round($grandTotalExpenditure, 2),
    //         'Balance' => round($grandTotalBalance, 2),
    //     ];

    //     return $results;
    // }

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

            // Define all rows (same as in getData)
            $educationRows = [
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 80, 'project' => 2, 'sub_project' => 0, 'object' => 1305],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 310, 'program' => 81, 'project' => 3, 'sub_project' => 0, 'object' => 1305]
            ];

            $westernMedicineRows = [
                ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 71, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 72, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 305, 'program' => 72, 'project' => 3, 'sub_project' => 0, 'object' => 1304]
            ];

            $indigenousMedicineRows = [
                ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 307, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 307, 'program' => 73, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 307, 'program' => 73, 'project' => 3, 'sub_project' => 0, 'object' => 1303]
            ];

            $roadsIrrigationRows = [
                ['trno' => 308, 'program' => 50, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 316, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 316, 'program' => 43, 'project' => 2, 'sub_project' => 0, 'object' => 1304]
            ];

            $agricultureRows = [
                ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 315, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 315, 'program' => 44, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 315, 'program' => 44, 'project' => 3, 'sub_project' => 0, 'object' => 1304]
            ];

            $probationChildcareRows = [
                ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 319, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 319, 'program' => 95, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 319, 'program' => 95, 'project' => 4, 'sub_project' => 0, 'object' => 1303]
            ];

            $socialServicesRows = [
                ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 306, 'program' => 52, 'project' => 2, 'sub_project' => 0, 'object' => 1304]
            ];

            $localGovernmentRows = [
                ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 312, 'program' => 45, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 312, 'program' => 45, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 312, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303]
            ];

            $livestockRows = [
                ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 300, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 300, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 301, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 301, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 302, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 1304],
                ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 303, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 304, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 304, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 304, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 306, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 308, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 308, 'program' => 3, 'project' => 2, 'sub_project' => 1, 'object' => 1304],
                ['trno' => 308, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 308, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 309, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 309, 'program' => 40, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 311, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 311, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 313, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 313, 'program' => 51, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 314, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 314, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 317, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 317, 'program' => 53, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 3, 'project' => 1, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 61, 'project' => 2, 'sub_project' => 0, 'object' => 1304],
                ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 90, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 318, 'program' => 93, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 320, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 320, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 320, 'program' => 3, 'project' => 4, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 320, 'program' => 3, 'project' => 4, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 320, 'program' => 3, 'project' => 5, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 320, 'program' => 3, 'project' => 5, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 321, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 322, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 322, 'program' => 3, 'project' => 3, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 323, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 324, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303],
                ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1301],
                ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1302],
                ['trno' => 325, 'program' => 3, 'project' => 2, 'sub_project' => 0, 'object' => 1303]
            ];

            // Add subject names
            $educationRows = $this->addSubjectNames($educationRows);
            $westernMedicineRows = $this->addSubjectNames($westernMedicineRows);
            $indigenousMedicineRows = $this->addSubjectNames($indigenousMedicineRows);
            $roadsIrrigationRows = $this->addSubjectNames($roadsIrrigationRows);
            $agricultureRows = $this->addSubjectNames($agricultureRows);
            $probationChildcareRows = $this->addSubjectNames($probationChildcareRows);
            $socialServicesRows = $this->addSubjectNames($socialServicesRows);
            $localGovernmentRows = $this->addSubjectNames($localGovernmentRows);
            $livestockRows = $this->addSubjectNames($livestockRows);

            // Process all data
            $educationData = $this->processRowsForExport($educationRows, $year, $monthsToInclude);
            $westernMedicineData = $this->processRowsForExport($westernMedicineRows, $year, $monthsToInclude);
            $indigenousMedicineData = $this->processRowsForExport($indigenousMedicineRows, $year, $monthsToInclude);
            $roadsIrrigationData = $this->processRowsForExport($roadsIrrigationRows, $year, $monthsToInclude);
            $agricultureData = $this->processRowsForExport($agricultureRows, $year, $monthsToInclude);
            $probationChildcareData = $this->processRowsForExport($probationChildcareRows, $year, $monthsToInclude);
            $socialServicesData = $this->processRowsForExport($socialServicesRows, $year, $monthsToInclude);
            $localGovernmentData = $this->processRowsForExport($localGovernmentRows, $year, $monthsToInclude);
            $livestockData = $this->processRowsForExport($livestockRows, $year, $monthsToInclude);

            // Build export data as array of objects
            $exportData = [];

            // Helper to add category data
            $addCategoryData = function($data, $categoryName) use (&$exportData) {
                if (empty($data)) return;
                
                // Add category header
                $exportData[] = (object) [
                    'Category' => $categoryName,
                    'TR No' => '',
                    'Program' => '',
                    'Project' => '',
                    'Sub Project' => '',
                    'Object' => '',
                    'Subject Name' => '',
                    'Allocation' => '',
                    'Expenditure' => '',
                    'Balance' => ''
                ];
                
                // Add data rows
                foreach ($data as $row) {
                    $exportData[] = (object) [
                        'Category' => '',
                        'TR No' => $row['TR No'] ?? '',
                        'Program' => $row['Program'] ?? '',
                        'Project' => $row['Project'] ?? '',
                        'Sub Project' => $row['Sub Project'] ?? '',
                        'Object' => $row['Object'] ?? '',
                        'Subject Name' => $row['Subject Name'] ?? '',
                        'Allocation' => round($row['Allocation'] ?? 0, 2),
                        'Expenditure' => round($row['Expenditure'] ?? 0, 2),
                        'Balance' => round($row['Balance'] ?? 0, 2)
                    ];
                }
                
                // Add empty row for spacing
                $exportData[] = (object) [
                    'Category' => '',
                    'TR No' => '',
                    'Program' => '',
                    'Project' => '',
                    'Sub Project' => '',
                    'Object' => '',
                    'Subject Name' => '',
                    'Allocation' => '',
                    'Expenditure' => '',
                    'Balance' => ''
                ];
            };

            // Add all categories
            $addCategoryData($educationData, 'EDUCATION (UPKEEP)');
            $addCategoryData($westernMedicineData, 'WESTERN MEDICINE');
            $addCategoryData($indigenousMedicineData, 'INDIGENOUS MEDICINE');
            $addCategoryData($roadsIrrigationData, 'ROADS & IRRIGATION');
            $addCategoryData($agricultureData, 'AGRICULTURE');
            $addCategoryData($probationChildcareData, 'PROBATION & CHILDCARE');
            $addCategoryData($socialServicesData, 'SOCIAL SERVICES');
            $addCategoryData($localGovernmentData, 'LOCAL GOVERNMENT');
            $addCategoryData($livestockData, 'LIVESTOCK');

            // Add Summary section
            $exportData[] = (object) [
                'Category' => '=== SUMMARY ===',
                'TR No' => '',
                'Program' => '',
                'Project' => '',
                'Sub Project' => '',
                'Object' => '',
                'Subject Name' => '',
                'Allocation' => '',
                'Expenditure' => '',
                'Balance' => ''
            ];

            // Calculate totals from all categories
            $categories = [
                ['name' => 'EDUCATION (UPKEEP)', 'data' => $educationData],
                ['name' => 'WESTERN MEDICINE', 'data' => $westernMedicineData],
                ['name' => 'INDIGENOUS MEDICINE', 'data' => $indigenousMedicineData],
                ['name' => 'ROADS & IRRIGATION', 'data' => $roadsIrrigationData],
                ['name' => 'AGRICULTURE', 'data' => $agricultureData],
                ['name' => 'PROBATION & CHILDCARE', 'data' => $probationChildcareData],
                ['name' => 'SOCIAL SERVICES', 'data' => $socialServicesData],
                ['name' => 'LOCAL GOVERNMENT', 'data' => $localGovernmentData],
                ['name' => 'LIVESTOCK', 'data' => $livestockData]
            ];

            $grandTotalAllocation = 0;
            $grandTotalExpenditure = 0;
            $grandTotalBalance = 0;

            foreach ($categories as $category) {
                $totalRow = end($category['data']);
                if ($totalRow && isset($totalRow['Allocation'])) {
                    $allocation = $totalRow['Allocation'] ?? 0;
                    $expenditure = $totalRow['Expenditure'] ?? 0;
                    $balance = $totalRow['Balance'] ?? 0;

                    $exportData[] = (object) [
                        'Category' => $category['name'],
                        'TR No' => '',
                        'Program' => '',
                        'Project' => '',
                        'Sub Project' => '',
                        'Object' => '',
                        'Subject Name' => 'TOTAL',
                        'Allocation' => round($allocation, 2),
                        'Expenditure' => round($expenditure, 2),
                        'Balance' => round($balance, 2)
                    ];

                    $grandTotalAllocation += $allocation;
                    $grandTotalExpenditure += $expenditure;
                    $grandTotalBalance += $balance;
                }
            }

            // Add Grand Total
            $exportData[] = (object) [
                'Category' => 'GRAND TOTAL',
                'TR No' => '',
                'Program' => '',
                'Project' => '',
                'Sub Project' => '',
                'Object' => '',
                'Subject Name' => '',
                'Allocation' => round($grandTotalAllocation, 2),
                'Expenditure' => round($grandTotalExpenditure, 2),
                'Balance' => round($grandTotalBalance, 2)
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'total_records' => count($exportData)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in Upkeep export: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function processRowsForExport($rows, $year, $monthsToInclude)
    {
        $results = [];
        $grandTotalAllocation = 0;
        $grandTotalExpenditure = 0;
        $grandTotalBalance = 0;

        foreach ($rows as $row) {
            $allocation = Budget::where('head', $row['trno'])
                ->where('program', $row['program'])
                ->where('project', $row['project'])
                ->where('subproj', $row['sub_project'])
                ->where('object', $row['object'])
                ->sum('amount');

            $cumulativeExpenditure = 0;

            foreach ($monthsToInclude as $currentMonth) {
                $debitDR = MonthlyFincance::whereYear('created_at', $year)
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

                $debitCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 2000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $netDebit = $debitDR - $debitCR;

                $otherDebitDR = MonthlyFincance::whereYear('created_at', $year)
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

                $otherDebitCR = MonthlyFincance::whereYear('created_at', $year)
                    ->where('month', $currentMonth)
                    ->where('trno', '!=', $row['trno'])
                    ->where('head', $row['trno'])
                    ->where('program', $row['program'])
                    ->where('project', $row['project'])
                    ->where('sub_project', $row['sub_project'])
                    ->where('object', $row['object'])
                    ->where('dr_cr_code', 2000)
                    ->where('dr_cr', 'CR')
                    ->sum('cash_xe');

                $netOtherDebit = $otherDebitDR - $otherDebitCR;
                $cumulativeExpenditure += ($netDebit + $netOtherDebit);
            }

            $balance = $allocation - $cumulativeExpenditure;

            $results[] = [
                'TR No' => $row['trno'],
                'Program' => $row['program'],
                'Project' => $row['project'],
                'Sub Project' => $row['sub_project'],
                'Object' => $row['object'],
                'Subject Name' => $row['subject_name'],
                'Allocation' => round($allocation, 2),
                'Expenditure' => round($cumulativeExpenditure, 2),
                'Balance' => round($balance, 2),
            ];

            $grandTotalAllocation += $allocation;
            $grandTotalExpenditure += $cumulativeExpenditure;
            $grandTotalBalance += $balance;
        }

        $results[] = [
            'TR No' => 'TOTAL',
            'Program' => '',
            'Project' => '',
            'Sub Project' => '',
            'Object' => '',
            'Subject Name' => '',
            'Allocation' => round($grandTotalAllocation, 2),
            'Expenditure' => round($grandTotalExpenditure, 2),
            'Balance' => round($grandTotalBalance, 2),
        ];

        return $results;
    }
}