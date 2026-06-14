<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\BudgetImport;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
{
    // Get all budget records
    public function index(Request $request)
    {
        try {
            $query = Budget::query();
            
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
            if ($request->filled('objname')) {
                $query->where('objname', 'like', '%' . $request->objname . '%');
            }
            
            $perPage = $request->get('per_page', 10);
            $records = $query->orderBy('id', 'asc')->paginate($perPage);
            $totalBudget = $query->sum('amount');
            
            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                    'last_page' => $records->lastPage(),
                ],
                'total_budget' => $totalBudget
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Import Excel file
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
            
            $import = new BudgetImport();
            Excel::import($import, $request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => 'Budget data imported successfully',
                'imported_count' => $import->getImportedCount(),
                'skipped_count' => $import->getSkippedCount()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Get summary
    public function getSummary()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_budget' => Budget::sum('amount'),
                    'total_records' => Budget::count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Add other methods (store, update, delete, etc.) as needed
    public function store(Request $request)
    {
        try {
            $record = Budget::create($request->all());
            return response()->json(['success' => true, 'data' => $record], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        try {
            $record = Budget::find($id);
            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }
            $record->update($request->all());
            return response()->json(['success' => true, 'data' => $record]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $record = Budget::find($id);
            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }
            $record->delete();
            return response()->json(['success' => true, 'message' => 'Deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}