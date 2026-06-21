<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BudgetController;
use App\Http\Controllers\API\AuthController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');


// Test route
Route::get('/hello', function () {
    return response()->json(['message' => 'API is working!']);
})->middleware('auth:sanctum');

// Budget routes
// Route::prefix('budget')->group(function () {
//     Route::get('/records', [BudgetController::class, 'index'])->middleware('auth:sanctum');
//     Route::post('/import', [BudgetController::class, 'import'])->middleware('auth:sanctum');
//     Route::get('/summary', [BudgetController::class, 'getSummary'])->middleware('auth:sanctum');
//     Route::post('/records', [BudgetController::class, 'store'])->middleware('auth:sanctum');
//     Route::put('/records/{id}', [BudgetController::class, 'update'])->middleware('auth:sanctum');
//     Route::delete('/records/{id}', [BudgetController::class, 'destroy'])->middleware('auth:sanctum');
// });

Route::prefix('budget')->middleware('auth:sanctum')->group(function () {
    Route::get('/records', [BudgetController::class, 'index']);
    Route::get('/records/{id}', [BudgetController::class, 'show']);
    Route::post('/records', [BudgetController::class, 'store']);
    Route::put('/records/{id}', [BudgetController::class, 'update']);
    Route::delete('/records/{id}', [BudgetController::class, 'destroy']);
    Route::delete('/records', [BudgetController::class, 'bulkDelete']);
    Route::post('/import', [BudgetController::class, 'import']);
    Route::get('/summary', [BudgetController::class, 'getSummary']);
    Route::get('/export', [BudgetController::class, 'export']);
    Route::get('/filter-options', [BudgetController::class, 'getFilterOptions']);
});



use App\Http\Controllers\API\SupplementaryController;

// Supplementary Budget Routes (Manual Entry Only)
Route::prefix('supplementary')->group(function () {
    Route::get('/records', [SupplementaryController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/records/{id}', [SupplementaryController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/records', [SupplementaryController::class, 'store'])->middleware('auth:sanctum');
    Route::put('/records/{id}', [SupplementaryController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/records/{id}', [SupplementaryController::class, 'destroy'])->middleware('auth:sanctum');
    Route::delete('/records', [SupplementaryController::class, 'destroyMultiple'])->middleware('auth:sanctum');
    Route::get('/filter-options', [SupplementaryController::class, 'getFilterOptions'])->middleware('auth:sanctum');
    Route::get('/summary', [SupplementaryController::class, 'getSummary'])->middleware('auth:sanctum');
});


use App\Http\Controllers\API\MonthlyFincanceController;

// Monthly Finance Routes
Route::prefix('monthly-finance')->group(function () {
    Route::get('/records', [MonthlyFincanceController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/records/{id}', [MonthlyFincanceController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/records', [MonthlyFincanceController::class, 'store'])->middleware('auth:sanctum');
    Route::put('/records/{id}', [MonthlyFincanceController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/records/{id}', [MonthlyFincanceController::class, 'destroy'])->middleware('auth:sanctum');
    Route::delete('/records', [MonthlyFincanceController::class, 'destroyMultiple'])->middleware('auth:sanctum');
    Route::post('/import', [MonthlyFincanceController::class, 'import'])->middleware('auth:sanctum');
    Route::get('/filter-options', [MonthlyFincanceController::class, 'getFilterOptions'])->middleware('auth:sanctum');
    Route::get('/summary', [MonthlyFincanceController::class, 'getSummary'])->middleware('auth:sanctum');
    Route::get('/export', [MonthlyFincanceController::class, 'export'])->middleware('auth:sanctum');
});

use App\Http\Controllers\API\OpeningBalanceController;

// Opening Balance Routes
Route::prefix('opening-balance')->group(function () {
    Route::get('/records', [OpeningBalanceController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/records/{id}', [OpeningBalanceController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/records', [OpeningBalanceController::class, 'store'])->middleware('auth:sanctum');
    Route::put('/records/{id}', [OpeningBalanceController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/records/{id}', [OpeningBalanceController::class, 'destroy'])->middleware('auth:sanctum');
    Route::delete('/records', [OpeningBalanceController::class, 'destroyMultiple'])->middleware('auth:sanctum');
    Route::post('/import', [OpeningBalanceController::class, 'import'])->middleware('auth:sanctum');
    Route::get('/filter-options', [OpeningBalanceController::class, 'getFilterOptions'])->middleware('auth:sanctum');
    Route::get('/summary', [OpeningBalanceController::class, 'getSummary'])->middleware('auth:sanctum');
    Route::get('/export', [OpeningBalanceController::class, 'export'])->middleware('auth:sanctum');
});

use App\Http\Controllers\API\ImpressIssueController;

// Impress Issue Routes
Route::prefix('impress-issue')->group(function () {
    Route::get('/records', [ImpressIssueController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/records/{id}', [ImpressIssueController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/records', [ImpressIssueController::class, 'store'])->middleware('auth:sanctum');
    Route::put('/records/{id}', [ImpressIssueController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/records/{id}', [ImpressIssueController::class, 'destroy'])->middleware('auth:sanctum');
    Route::delete('/records', [ImpressIssueController::class, 'destroyMultiple'])->middleware('auth:sanctum');
    Route::post('/import', [ImpressIssueController::class, 'import'])->middleware('auth:sanctum');
    Route::get('/filter-options', [ImpressIssueController::class, 'getFilterOptions'])->middleware('auth:sanctum');
    Route::get('/summary', [ImpressIssueController::class, 'getSummary'])->middleware('auth:sanctum');
    Route::get('/export', [ImpressIssueController::class, 'export'])->middleware('auth:sanctum');
});


use App\Http\Controllers\API\ImpressSettlementController;

// Impress Settlement Routes
Route::prefix('impress-settlement')->group(function () {
    Route::get('/records', [ImpressSettlementController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/records/{id}', [ImpressSettlementController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/records', [ImpressSettlementController::class, 'store'])->middleware('auth:sanctum');
    Route::put('/records/{id}', [ImpressSettlementController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/records/{id}', [ImpressSettlementController::class, 'destroy'])->middleware('auth:sanctum');
    Route::delete('/records', [ImpressSettlementController::class, 'destroyMultiple'])->middleware('auth:sanctum');
    Route::post('/import', [ImpressSettlementController::class, 'import'])->middleware('auth:sanctum');
    Route::get('/filter-options', [ImpressSettlementController::class, 'getFilterOptions'])->middleware('auth:sanctum');
    Route::get('/summary', [ImpressSettlementController::class, 'getSummary'])->middleware('auth:sanctum');
    Route::get('/export', [ImpressSettlementController::class, 'export'])->middleware('auth:sanctum');
});

use App\Http\Controllers\API\NetExpenditureController;

// Net Expenditure Routes
Route::prefix('net-expenditure')->group(function () {
    Route::get('/data', [NetExpenditureController::class, 'getData'])->middleware('auth:sanctum');
    Route::get('/filter-options', [NetExpenditureController::class, 'getFilterOptionsEndpoint'])->middleware('auth:sanctum');
    Route::get('/export', [NetExpenditureController::class, 'export'])->middleware('auth:sanctum');
});



use App\Http\Controllers\API\NetAllocationController;


// Net Allocation Routes
Route::prefix('net-allocation')->middleware('auth:sanctum')->group(function () {
    // Get data with filters
    Route::get('/data', [NetAllocationController::class, 'getData']);
    
    // Get filter options (dropdown values)
    Route::get('/filter-options', [NetAllocationController::class, 'getFilterOptionsEndpoint']);
    
   
});

use App\Http\Controllers\API\UserController;

Route::middleware('auth:sanctum')->group(function () {
    // User profile routes
    Route::get('/user/profile', [UserController::class, 'getProfile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    
    // ... other routes
});


use App\Http\Controllers\API\WOPController;


// WOP Routes
Route::prefix('wop')->middleware('auth:sanctum')->group(function () {
    Route::get('/data', [WOPController::class, 'getData']);
    Route::get('/filter-options', [WOPController::class, 'getFilterOptions']);
    Route::get('/export', [WOPController::class, 'export']);
});


use App\Http\Controllers\API\COEOWController;


// COEOW Routes (Classification of Expenditure - Object Wise)
Route::prefix('coeow')->middleware('auth:sanctum')->group(function () {
    Route::get('/data', [COEOWController::class, 'getData']);
    Route::get('/filter-options', [COEOWController::class, 'getFilterOptions']);
    Route::get('/export', [COEOWController::class, 'export']);
});


use App\Http\Controllers\API\COEHWController;


// COEHW (Classification of Expenditure Head Wise) Routes
Route::prefix('coehw')->middleware('auth:sanctum')->group(function () {
    Route::get('/data', [COEHWController::class, 'getData']);
    Route::get('/filter-options', [COEHWController::class, 'getFilterOptions']);
    Route::get('/export', [COEHWController::class, 'export']);
});


use App\Http\Controllers\API\RCExpenditureController;


// RC Expenditure Routes
Route::prefix('rc-expenditure')->middleware('auth:sanctum')->group(function () {
    Route::get('/data', [RCExpenditureController::class, 'getData']);
    Route::get('/filter-options', [RCExpenditureController::class, 'getFilterOptions']);
    Route::get('/export', [RCExpenditureController::class, 'export']);
});