<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HeadInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HeadInfoController extends Controller
{
    /**
     * Display a listing of all heads.
     */
    public function index(Request $request)
    {
        try {
            $query = HeadInfo::query();
            
            // Apply filters
            if ($request->filled('head')) {
                $query->where('head', $request->head);
            }
            if ($request->filled('description')) {
                $query->where('description', 'like', '%' . $request->description . '%');
            }
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('head', 'asc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                    'last_page' => $records->lastPage(),
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
     * Store a newly created head.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'head' => 'required|integer|unique:head_info,head',
                'description' => 'nullable|string|max:255'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $record = HeadInfo::create([
                'head' => $request->head,
                'description' => $request->description
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Head created successfully',
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
     * Display the specified head.
     */
    public function show($head)
    {
        try {
            $record = HeadInfo::find($head);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Head not found'
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
     * Update the specified head.
     */
    public function update(Request $request, $head)
    {
        try {
            $record = HeadInfo::find($head);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Head not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'head' => 'sometimes|integer|unique:head_info,head,' . $head . ',head',
                'description' => 'nullable|string|max:255'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Only update description (head is primary key and cannot be changed)
            if ($request->has('description')) {
                $record->description = $request->description;
                $record->save();
            }
            
            // If head is provided in request, check if it's different
            if ($request->has('head') && $request->head != $head) {
                return response()->json([
                    'success' => false,
                    'message' => 'Head code cannot be changed as it is the primary key'
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Head updated successfully',
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
     * Remove the specified head.
     */
    public function destroy($head)
    {
        try {
            $record = HeadInfo::find($head);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Head not found'
                ], 404);
            }
            
            // Check if head is being used in impress_issues
            $usedCount = $record->impressIssues()->count();
            if ($usedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete head as it's being used in {$usedCount} impress issue(s). Please delete the impress issues first."
                ], 422);
            }
            
            $record->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Head deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple heads.
     */
    public function destroyMultiple(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'exists:head_info,head'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check if any head is being used
            $usedHeads = HeadInfo::whereIn('head', $request->ids)
                ->whereHas('impressIssues')
                ->pluck('head')
                ->toArray();
                
            if (!empty($usedHeads)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete heads: " . implode(', ', $usedHeads) . " as they're being used in impress issues. Please delete the impress issues first."
                ], 422);
            }
            
            $deletedCount = HeadInfo::whereIn('head', $request->ids)->delete();
            
            return response()->json([
                'success' => true,
                'message' => $deletedCount . ' head(s) deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all heads without pagination (for dropdowns)
     */
    public function getHeadsList()
    {
        try {
            $heads = HeadInfo::orderBy('head', 'asc')
                ->select('head', 'description')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $heads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search heads by description or head code
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:1'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $query = $request->query;
            
            $records = HeadInfo::where('head', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orderBy('head', 'asc')
                ->limit(10)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $records
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}