<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupplementaryRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplementaryController extends Controller
{
    /**
     * Get all supplementary records with filters and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = SupplementaryRecord::query();
            
            // Apply filters
            if ($request->filled('order_number')) {
                $query->where('order_number', 'like', '%' . $request->order_number . '%');
            }
            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }
            if ($request->filled('month')) {
                $query->where('month', $request->month);
            }
            if ($request->filled('head')) {
                $query->where('head', $request->head);
            }
            if ($request->filled('program')) {
                $query->where('program', $request->program);
            }
            if ($request->filled('project')) {
                $query->where('project', $request->project);
            }
            
            $perPage = $request->get('per_page', 10);
            $records = $query->orderBy('id', 'desc')->paginate($perPage);
            
            $totalFr66p = $query->sum('fr66p');
            $totalFr66m = $query->sum('fr66m');
            $totalSupplementary = $query->sum('supplementary_amount');
            
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
                    'fr66p' => $totalFr66p,
                    'fr66m' => $totalFr66m,
                    'supplementary' => $totalSupplementary
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
     * Get single record
     */
    public function show($id)
    {
        try {
            $record = SupplementaryRecord::find($id);
            
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
     * Create new record
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_number' => 'required|string|max:255',
                'year' => 'required|integer|digits:4',
                'month' => 'required|integer|between:1,12',
                'head' => 'nullable|integer',
                'program' => 'nullable|integer',
                'project' => 'nullable|integer',
                'subproject' => 'nullable|integer',
                'object' => 'nullable|integer',
                'subobject' => 'nullable|integer',
                'fr66p' => 'nullable|numeric',
                'fr66m' => 'nullable|numeric',
                'supplementary_amount' => 'nullable|numeric'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $record = SupplementaryRecord::create($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Supplementary record created successfully',
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
     * Update record
     */
    public function update(Request $request, $id)
    {
        try {
            $record = SupplementaryRecord::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'order_number' => 'required|string|max:255',
                'year' => 'required|integer|digits:4',
                'month' => 'required|integer|between:1,12',
                'head' => 'nullable|integer',
                'program' => 'nullable|integer',
                'project' => 'nullable|integer',
                'subproject' => 'nullable|integer',
                'object' => 'nullable|integer',
                'subobject' => 'nullable|integer',
                'fr66p' => 'nullable|numeric',
                'fr66m' => 'nullable|numeric',
                'supplementary_amount' => 'nullable|numeric'
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
                'message' => 'Record updated successfully',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating record: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete single record
     */
    public function destroy($id)
    {
        try {
            $record = SupplementaryRecord::find($id);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
            $record->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting record: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete multiple records
     */
    public function destroyMultiple(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'exists:supplementary_records,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $deletedCount = SupplementaryRecord::whereIn('id', $request->ids)->delete();
            
            return response()->json([
                'success' => true,
                'message' => $deletedCount . ' record(s) deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get filter options for dropdowns
     */
    public function getFilterOptions()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'years' => SupplementaryRecord::whereNotNull('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
                    'heads' => SupplementaryRecord::whereNotNull('head')->distinct()->orderBy('head')->pluck('head'),
                    'programs' => SupplementaryRecord::whereNotNull('program')->distinct()->orderBy('program')->pluck('program'),
                    'projects' => SupplementaryRecord::whereNotNull('project')->distinct()->orderBy('project')->pluck('project'),
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
     * Get summary statistics
     */
    public function getSummary()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_fr66p' => SupplementaryRecord::sum('fr66p'),
                    'total_fr66m' => SupplementaryRecord::sum('fr66m'),
                    'total_supplementary' => SupplementaryRecord::sum('supplementary_amount'),
                    'total_records' => SupplementaryRecord::count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}