<?php

// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use App\Models\Budget;
// use Illuminate\Http\Request;
// use Maatwebsite\Excel\Facades\Excel;
// use App\Imports\BudgetImport;
// use Illuminate\Support\Facades\Validator;

// class BudgetController extends Controller
// {
//     // Get all budget records
//     public function index(Request $request)
//     {
//        try {
//         $query = Budget::query();
        
//         // Apply filters
//         if ($request->filled('head')) {
//             $query->where('head', $request->head);
//         }
//         if ($request->filled('program')) {
//             $query->where('program', $request->program);
//         }
//         if ($request->filled('project')) {
//             $query->where('project', $request->project);
//         }
//         if ($request->filled('object')) {
//             $query->where('object', $request->object);
//         }
//         if ($request->filled('objname')) {
//             $query->where('objname', 'like', '%' . $request->objname . '%');
//         }
//         if ($request->filled('min_amount')) {
//             $query->where('amount', '>=', $request->min_amount);
//         }
//         if ($request->filled('max_amount')) {
//             $query->where('amount', '<=', $request->max_amount);
//         }
        
//         // Apply sorting: Head, Program, Project, Sub Project, Object (all ascending)
//         $query->orderBy('head', 'asc')
//               ->orderBy('program', 'asc')
//               ->orderBy('project', 'asc')
//               ->orderBy('subproj', 'asc')
//               ->orderBy('object', 'asc');
        
//         $perPage = $request->get('per_page', 10);
        
//         // Handle "all" per_page parameter
//         if ($perPage === 'all' || $perPage === 'All' || $perPage === 'ALL') {
//             $records = $query->get();
//             $totalBudget = $query->sum('amount');
            
//             return response()->json([
//                 'success' => true,
//                 'data' => $records,
//                 'pagination' => [
//                     'current_page' => 1,
//                     'per_page' => $records->count(),
//                     'total' => $records->count(),
//                     'last_page' => 1,
//                 ],
//                 'total_budget' => $totalBudget
//             ]);
//         }
        
//         $records = $query->paginate($perPage);
//         $totalBudget = $query->sum('amount');
        
//         return response()->json([
//             'success' => true,
//             'data' => $records->items(),
//             'pagination' => [
//                 'current_page' => $records->currentPage(),
//                 'per_page' => $records->perPage(),
//                 'total' => $records->total(),
//                 'last_page' => $records->lastPage(),
//             ],
//             'total_budget' => $totalBudget
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => $e->getMessage()
//         ], 500);
//     }
//     }
    
//     // Import Excel file
//     public function import(Request $request)
//     {
//         try {
//             $validator = Validator::make($request->all(), [
//                 'file' => 'required|file|mimes:xlsx,xls,csv|max:51200'
//             ]);
            
//             if ($validator->fails()) {
//                 return response()->json([
//                     'success' => false,
//                     'errors' => $validator->errors()
//                 ], 422);
//             }
            
//             $import = new BudgetImport();
//             Excel::import($import, $request->file('file'));
            
//             $responseData = [
//                 'success' => true,
//                 'message' => 'Budget data imported successfully',
//                 'imported_count' => $import->getImportedCount(),
//                 'skipped_count' => $import->getSkippedCount()
//             ];
            
//             // Optional: Include skipped reasons if you want to debug
//             if ($import->getSkippedCount() > 0 && method_exists($import, 'getSkippedReasons')) {
//                 $responseData['skipped_reasons'] = $import->getSkippedReasons();
//             }
            
//             return response()->json($responseData);
            
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Import failed: ' . $e->getMessage()
//             ], 500);
//         }
//     }
    
//     // Get summary
//     public function getSummary()
//     {
//         try {
//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'total_budget' => Budget::sum('amount'),
//                     'total_records' => Budget::count(),
//                     'total_records_with_amount' => Budget::whereNotNull('amount')->count(),
//                     'total_records_without_amount' => Budget::whereNull('amount')->count(),
//                 ]
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
    
//     // Get single record
//     public function show($id)
//     {
//         try {
//             $record = Budget::find($id);
//             if (!$record) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Record not found'
//                 ], 404);
//             }
            
//             return response()->json([
//                 'success' => true,
//                 'data' => $record
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
    
//     // Create new record
//     public function store(Request $request)
//     {
//         try {
//             $validator = Validator::make($request->all(), [
//                 'head' => 'nullable|integer',
//                 'program' => 'nullable|integer',
//                 'project' => 'nullable|integer',
//                 'subproj' => 'nullable|integer',
//                 'object' => 'nullable|integer',
//                 'obj_detail' => 'nullable|string',
//                 'funding' => 'nullable|integer',
//                 'objname' => 'required|string',
//                 'amount' => 'required|numeric'
//             ]);
            
//             if ($validator->fails()) {
//                 return response()->json([
//                     'success' => false,
//                     'errors' => $validator->errors()
//                 ], 422);
//             }
            
//             $record = Budget::create([
//                 'head' => $request->head,
//                 'program' => $request->program,
//                 'project' => $request->project,
//                 'subproj' => $request->subproj,
//                 'object' => $request->object,
//                 'obj_detail' => $request->obj_detail,
//                 'funding' => $request->funding,
//                 'objname' => $request->objname,
//                 'amount' => $request->amount
//             ]);
            
//             return response()->json([
//                 'success' => true,
//                 'data' => $record,
//                 'message' => 'Record created successfully'
//             ], 201);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
    
//     // Update record
//     public function update(Request $request, $id)
//     {
//         try {
//             $record = Budget::find($id);
//             if (!$record) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Record not found'
//                 ], 404);
//             }
            
//             $validator = Validator::make($request->all(), [
//                 'head' => 'nullable|integer',
//                 'program' => 'nullable|integer',
//                 'project' => 'nullable|integer',
//                 'subproj' => 'nullable|integer',
//                 'object' => 'nullable|integer',
//                 'obj_detail' => 'nullable|string',
//                 'funding' => 'nullable|integer',
//                 'objname' => 'sometimes|required|string',
//                 'amount' => 'sometimes|required|numeric'
//             ]);
            
//             if ($validator->fails()) {
//                 return response()->json([
//                     'success' => false,
//                     'errors' => $validator->errors()
//                 ], 422);
//             }
            
//             $record->update($request->all());
            
//             return response()->json([
//                 'success' => true,
//                 'data' => $record,
//                 'message' => 'Record updated successfully'
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
    
//     // Delete single record
//     public function destroy($id)
//     {
//         try {
//             $record = Budget::find($id);
//             if (!$record) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Record not found'
//                 ], 404);
//             }
            
//             $record->delete();
            
//             return response()->json([
//                 'success' => true,
//                 'message' => 'Record deleted successfully'
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
    
//     // Bulk delete records
//     public function bulkDelete(Request $request)
//     {
//         try {
//             $validator = Validator::make($request->all(), [
//                 'ids' => 'required|array',
//                 'ids.*' => 'exists:budgets,id'
//             ]);
            
//             if ($validator->fails()) {
//                 return response()->json([
//                     'success' => false,
//                     'errors' => $validator->errors()
//                 ], 422);
//             }
            
//             $deletedCount = Budget::whereIn('id', $request->ids)->delete();
            
//             return response()->json([
//                 'success' => true,
//                 'message' => "$deletedCount record(s) deleted successfully",
//                 'deleted_count' => $deletedCount
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
    
//     // Export all records
//     public function export()
//     {
//         try {
//             $records = Budget::all();
            
//             return response()->json([
//                 'success' => true,
//                 'data' => $records,
//                 'total_records' => $records->count()
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
    
//     // Get distinct filter options (for dropdown filters)
//     public function getFilterOptions()
//     {
//         try {
//             return response()->json([
//                 'success' => true,
//                 'data' => [
//                     'heads' => Budget::select('head')->whereNotNull('head')->distinct()->orderBy('head')->pluck('head'),
//                     'programs' => Budget::select('program')->whereNotNull('program')->distinct()->orderBy('program')->pluck('program'),
//                     'projects' => Budget::select('project')->whereNotNull('project')->distinct()->orderBy('project')->pluck('project'),
//                     'objects' => Budget::select('object')->whereNotNull('object')->distinct()->orderBy('object')->pluck('object'),
//                     'objnames' => Budget::select('objname')->whereNotNull('objname')->distinct()->orderBy('objname')->pluck('objname'),
//                 ]
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
// }




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
            if ($request->filled('subproj')) {
                $query->where('subproj', $request->subproj);
            }
            if ($request->filled('object')) {
                $query->where('object', $request->object);
            }
            if ($request->filled('objname')) {
                $query->where('objname', 'like', '%' . $request->objname . '%');
            }
            if ($request->filled('min_amount')) {
                $query->where('amount', '>=', $request->min_amount);
            }
            if ($request->filled('max_amount')) {
                $query->where('amount', '<=', $request->max_amount);
            }
            
            // Apply sorting: Head, Program, Project, Sub Project, Object (all ascending)
            $query->orderBy('head', 'asc')
                  ->orderBy('program', 'asc')
                  ->orderBy('project', 'asc')
                  ->orderBy('subproj', 'asc')
                  ->orderBy('object', 'asc');
            
            $perPage = $request->get('per_page', 10);
            
            // Handle "all" per_page parameter
            if ($perPage === 'all' || $perPage === 'All' || $perPage === 'ALL') {
                $records = $query->get();
                $totalBudget = $query->sum('amount');
                
                return response()->json([
                    'success' => true,
                    'data' => $records,
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => $records->count(),
                        'total' => $records->count(),
                        'last_page' => 1,
                    ],
                    'total_budget' => $totalBudget
                ]);
            }
            
            $records = $query->paginate($perPage);
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
            
            $responseData = [
                'success' => true,
                'message' => 'Budget data imported successfully',
                'imported_count' => $import->getImportedCount(),
                'skipped_count' => $import->getSkippedCount()
            ];
            
            // Optional: Include skipped reasons if you want to debug
            if ($import->getSkippedCount() > 0 && method_exists($import, 'getSkippedReasons')) {
                $responseData['skipped_reasons'] = $import->getSkippedReasons();
            }
            
            return response()->json($responseData);
            
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
                    'total_records_with_amount' => Budget::whereNotNull('amount')->count(),
                    'total_records_without_amount' => Budget::whereNull('amount')->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Get single record
    public function show($id)
    {
        try {
            $record = Budget::find($id);
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
    
    // Create new record
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'head' => 'nullable|integer',
                'program' => 'nullable|integer',
                'project' => 'nullable|integer',
                'subproj' => 'nullable|integer',
                'object' => 'nullable|integer',
                'obj_detail' => 'nullable|string',
                'funding' => 'nullable|integer',
                'objname' => 'required|string',
                'amount' => 'required|numeric'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $record = Budget::create([
                'head' => $request->head,
                'program' => $request->program,
                'project' => $request->project,
                'subproj' => $request->subproj,
                'object' => $request->object,
                'obj_detail' => $request->obj_detail,
                'funding' => $request->funding,
                'objname' => $request->objname,
                'amount' => $request->amount
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $record,
                'message' => 'Record created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Update record
    public function update(Request $request, $id)
    {
        try {
            $record = Budget::find($id);
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
                'subproj' => 'nullable|integer',
                'object' => 'nullable|integer',
                'obj_detail' => 'nullable|string',
                'funding' => 'nullable|integer',
                'objname' => 'sometimes|required|string',
                'amount' => 'sometimes|required|numeric'
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
                'data' => $record,
                'message' => 'Record updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Delete single record
    public function destroy($id)
    {
        try {
            $record = Budget::find($id);
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
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Bulk delete records
    public function bulkDelete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'exists:budgets,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $deletedCount = Budget::whereIn('id', $request->ids)->delete();
            
            return response()->json([
                'success' => true,
                'message' => "$deletedCount record(s) deleted successfully",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Export all records
    public function export()
    {
        try {
            $records = Budget::orderBy('head', 'asc')
                            ->orderBy('program', 'asc')
                            ->orderBy('project', 'asc')
                            ->orderBy('subproj', 'asc')
                            ->orderBy('object', 'asc')
                            ->get();
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'total_records' => $records->count(),
                'total_budget' => $records->sum('amount')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Get distinct filter options (for dropdown filters)
    public function getFilterOptions()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'heads' => Budget::select('head')->whereNotNull('head')->distinct()->orderBy('head')->pluck('head'),
                    'programs' => Budget::select('program')->whereNotNull('program')->distinct()->orderBy('program')->pluck('program'),
                    'projects' => Budget::select('project')->whereNotNull('project')->distinct()->orderBy('project')->pluck('project'),
                    'subprojs' => Budget::select('subproj')->whereNotNull('subproj')->distinct()->orderBy('subproj')->pluck('subproj'),
                    'objects' => Budget::select('object')->whereNotNull('object')->distinct()->orderBy('object')->pluck('object'),
                    'objnames' => Budget::select('objname')->whereNotNull('objname')->distinct()->orderBy('objname')->pluck('objname'),
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