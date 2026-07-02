<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ItemCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemCodeController extends Controller
{
    /**
     * Display a listing of all items.
     */
    public function index(Request $request)
    {
        try {
            $query = ItemCode::query();
            
            // Apply filters
            if ($request->filled('item')) {
                $query->where('item', $request->item);
            }
            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }
            if ($request->filled('description')) {
                $query->where('description', 'like', '%' . $request->description . '%');
            }
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $records = $query->orderBy('year', 'desc')
                ->orderBy('item', 'asc')
                ->paginate($perPage);
            
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
     * Store a newly created item.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'item' => 'required|integer|unique:item_code,item',
                'year' => 'required|integer|digits:4|min:1900|max:' . date('Y'),
                'description' => 'nullable|string|max:255'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $record = ItemCode::create([
                'item' => $request->item,
                'year' => $request->year,
                'description' => $request->description
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Item created successfully',
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
     * Display the specified item.
     */
    public function show($item)
    {
        try {
            $record = ItemCode::find($item);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found'
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
     * Update the specified item.
     */
    public function update(Request $request, $item)
    {
        try {
            $record = ItemCode::find($item);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'item' => 'sometimes|integer|unique:item_code,item,' . $item . ',item',
                'year' => 'sometimes|integer|digits:4|min:1900|max:' . date('Y'),
                'description' => 'nullable|string|max:255'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check if trying to change item code
            if ($request->has('item') && $request->item != $item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item code cannot be changed as it is the primary key'
                ], 422);
            }
            
            // Update fields
            if ($request->has('year')) {
                $record->year = $request->year;
            }
            if ($request->has('description')) {
                $record->description = $request->description;
            }
            $record->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
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
     * Remove the specified item.
     */
    public function destroy($item)
    {
        try {
            $record = ItemCode::find($item);
            
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found'
                ], 404);
            }
            
            $record->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple items.
     */
    public function destroyMultiple(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'exists:item_code,item'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $deletedCount = ItemCode::whereIn('item', $request->ids)->delete();
            
            return response()->json([
                'success' => true,
                'message' => $deletedCount . ' item(s) deleted successfully'
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
                    'years' => ItemCode::whereNotNull('year')
                        ->distinct()
                        ->orderBy('year', 'desc')
                        ->pluck('year'),
                    'items' => ItemCode::whereNotNull('item')
                        ->distinct()
                        ->orderBy('item')
                        ->pluck('item'),
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
     * Get all items without pagination (for dropdowns)
     */
    public function getItemsList()
    {
        try {
            $items = ItemCode::orderBy('year', 'desc')
                ->orderBy('item', 'asc')
                ->select('item', 'year', 'description')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $items
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search items by description or item code
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
            
            $records = ItemCode::where('item', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhere('year', 'like', "%{$query}%")
                ->orderBy('year', 'desc')
                ->orderBy('item', 'asc')
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

    /**
     * Get years with items count
     */
    public function getYearsSummary()
    {
        try {
            $summary = ItemCode::selectRaw('year, COUNT(*) as count')
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}