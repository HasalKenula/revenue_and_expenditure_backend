<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Imports\EstimateImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class EstimateController extends Controller
{
    /**
     * Display a listing of all estimates.
     */
    public function index(Request $request)
    {
        try {
            $query = Estimate::query();
            
            // Apply filters
            if ($request->filled('head')) {
                $query->where('head', $request->head);
            }
            if ($request->filled('program')) {
                $query->where('program', $request->program);
            }
            if ($request->filled('project')) {
                $query->where('project', $request->project);
            }
            if ($request->filled('sub_project')) {
                $query->where('sub_project', $request->sub_project);
            }
            if ($request->filled('object')) {
                $query->where('object', $request->object);
            }
            if ($request->filled('revenue_code_name')) {
                $query->where('revenue_code_name', 'like', '%' . $request->revenue_code_name . '%');
            }
            if ($request->filled('min_estimate')) {
                $query->where('estimate', '>=', $request->min_estimate);
            }
            if ($request->filled('max_estimate')) {
                $query->where('estimate', '<=', $request->max_estimate);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('head', 'asc')
                ->orderBy('program', 'asc')
                ->paginate($perPage);
            
            // Calculate totals
            $totalEstimate = $query->sum('estimate');
            $totalReEstimate = $query->sum('re_estimate');
            
            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                    'last_page' => $records->lastPage(),
                ],
                'totals' => [
                    'total_estimate' => $totalEstimate,
                    'total_re_estimate' => $totalReEstimate,
                    'total_records' => $records->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created estimate.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'head' => 'nullable|integer',
                'program' => 'nullable|integer',
                'project' => 'nullable|integer',
                'sub_project' => 'nullable|integer',
                'object' => 'nullable|integer',
                'revenue_code_name' => 'nullable|string|max:255',
                'estimate' => 'nullable|numeric|min:0',
                're_estimate' => 'nullable|numeric|min:0',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $record = Estimate::create($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Estimate created successfully',
                'data' => $record
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified estimate.
     */
    public function show($id)
    {
        try {
            $record = Estimate::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified estimate.
     */
    public function update(Request $request, $id)
    {
        try {
            $record = Estimate::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'head' => 'nullable|integer',
                'program' => 'nullable|integer',
                'project' => 'nullable|integer',
                'sub_project' => 'nullable|integer',
                'object' => 'nullable|integer',
                'revenue_code_name' => 'nullable|string|max:255',
                'estimate' => 'nullable|numeric|min:0',
                're_estimate' => 'nullable|numeric|min:0',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $record->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Estimate updated successfully',
                'data' => $record->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified estimate.
     */
    public function destroy($id)
    {
        try {
            $record = Estimate::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $record->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Estimate deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple estimates.
     */
    // public function destroyMultiple(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'ids' => 'required|array',
    //             'ids.*' => 'exists:estimates,id'
    //         ]);
            
    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'errors' => $validator->errors()
    //             ], 422);
    //         }
            
    //         $deletedCount = Estimate::whereIn('id', $request->ids)->delete();
            
    //         return response()->json([
    //             'success' => true,
    //             'message' => $deletedCount . ' record(s) deleted successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error deleting records: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
    /**
     * Delete multiple estimates.
     */
    public function destroyMultiple(Request $request)
    {
        try {
            // Get IDs from query parameter
            $ids = $request->query('ids');
            
            // If IDs are sent as a comma-separated string
            if (is_string($ids)) {
                $ids = explode(',', $ids);
            }
            
            // If IDs are in the request body (as fallback)
            if (empty($ids)) {
                $ids = $request->input('ids');
                if (is_string($ids)) {
                    $ids = explode(',', $ids);
                }
            }
            
            // Validate
            if (empty($ids) || !is_array($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide at least one ID to delete'
                ], 422);
            }
            
            // Ensure all IDs are integers and remove empty values
            $ids = array_filter(array_map('intval', $ids), function($id) {
                return $id > 0;
            });
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid IDs provided'
                ], 422);
            }
            
            // Check if records exist
            $existingRecords = Estimate::whereIn('id', $ids)->pluck('id');
            $nonExistentIds = array_diff($ids, $existingRecords->toArray());
            
            if (!empty($nonExistentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some records were not found: ' . implode(', ', $nonExistentIds),
                    'not_found_ids' => $nonExistentIds
                ], 404);
            }
            
            // Perform deletion
            $deletedCount = Estimate::whereIn('id', $ids)->delete();
            
            return response()->json([
                'success' => true,
                'message' => $deletedCount . ' record(s) deleted successfully',
                'deleted_count' => $deletedCount,
                'deleted_ids' => $ids
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Excel file.
     */
    /**
 * Import Excel file.
 */
public function import(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $import = new EstimateImport();
        Excel::import($import, $request->file('file'));
        
        return response()->json([
            'success' => true,
            'message' => 'Estimates imported successfully',
            'imported_count' => $import->getImportedCount(),
            'skipped_count' => $import->getSkippedCount(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Import failed: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Get filter options for dropdowns.
     */
    public function getFilterOptions()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'heads' => Estimate::whereNotNull('head')->distinct()->orderBy('head')->pluck('head'),
                    'programs' => Estimate::whereNotNull('program')->distinct()->orderBy('program')->pluck('program'),
                    'projects' => Estimate::whereNotNull('project')->distinct()->orderBy('project')->pluck('project'),
                    'sub_projects' => Estimate::whereNotNull('sub_project')->distinct()->orderBy('sub_project')->pluck('sub_project'),
                    'objects' => Estimate::whereNotNull('object')->distinct()->orderBy('object')->pluck('object'),
                    'revenue_codes' => Estimate::whereNotNull('revenue_code_name')->distinct()->orderBy('revenue_code_name')->pluck('revenue_code_name'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary statistics.
     */
    public function getSummary()
    {
        try {
            $totalRecords = Estimate::count();
            $totalEstimate = Estimate::sum('estimate');
            $totalReEstimate = Estimate::sum('re_estimate');
            $avgEstimate = Estimate::avg('estimate');
            $maxEstimate = Estimate::max('estimate');
            $minEstimate = Estimate::min('estimate');
            
            // Summary by head
            $byHead = Estimate::selectRaw('head, COUNT(*) as count, SUM(estimate) as total_estimate, SUM(re_estimate) as total_re_estimate')
                ->groupBy('head')
                ->orderBy('head')
                ->get();
            
            // Summary by program
            $byProgram = Estimate::selectRaw('program, COUNT(*) as count, SUM(estimate) as total_estimate, SUM(re_estimate) as total_re_estimate')
                ->groupBy('program')
                ->orderBy('program')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $totalRecords,
                    'total_estimate' => $totalEstimate,
                    'total_re_estimate' => $totalReEstimate,
                    'average_estimate' => round($avgEstimate, 2),
                    'max_estimate' => $maxEstimate,
                    'min_estimate' => $minEstimate,
                    'by_head' => $byHead,
                    'by_program' => $byProgram,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export all records.
     */
    public function export()
    {
        try {
            $records = Estimate::orderBy('head', 'asc')
                ->orderBy('program', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'total_records' => $records->count(),
                'total_estimate' => $records->sum('estimate'),
                'total_re_estimate' => $records->sum('re_estimate'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}