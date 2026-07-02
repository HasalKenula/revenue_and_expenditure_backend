<?php

// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\API\BudgetController;
// use App\Http\Controllers\API\AuthController;


// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);
// Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');


// // Test route
// Route::get('/hello', function () {
//     return response()->json(['message' => 'API is working!']);
// })->middleware('auth:sanctum');

// // Budget routes
// // Route::prefix('budget')->group(function () {
// //     Route::get('/records', [BudgetController::class, 'index'])->middleware('auth:sanctum');
// //     Route::post('/import', [BudgetController::class, 'import'])->middleware('auth:sanctum');
// //     Route::get('/summary', [BudgetController::class, 'getSummary'])->middleware('auth:sanctum');
// //     Route::post('/records', [BudgetController::class, 'store'])->middleware('auth:sanctum');
// //     Route::put('/records/{id}', [BudgetController::class, 'update'])->middleware('auth:sanctum');
// //     Route::delete('/records/{id}', [BudgetController::class, 'destroy'])->middleware('auth:sanctum');
// // });

// Route::prefix('budget')->middleware('auth:sanctum')->group(function () {
//     Route::get('/records', [BudgetController::class, 'index']);
//     Route::get('/records/{id}', [BudgetController::class, 'show']);
//     Route::post('/records', [BudgetController::class, 'store']);
//     Route::put('/records/{id}', [BudgetController::class, 'update']);
//     Route::delete('/records/{id}', [BudgetController::class, 'destroy']);
//     Route::delete('/records', [BudgetController::class, 'bulkDelete']);
//     Route::post('/import', [BudgetController::class, 'import']);
//     Route::get('/summary', [BudgetController::class, 'getSummary']);
//     Route::get('/export', [BudgetController::class, 'export']);
//     Route::get('/filter-options', [BudgetController::class, 'getFilterOptions']);
// });



// use App\Http\Controllers\API\SupplementaryController;

// // Supplementary Budget Routes (Manual Entry Only)
// Route::prefix('supplementary')->group(function () {
//     Route::get('/records', [SupplementaryController::class, 'index'])->middleware('auth:sanctum');
//     Route::get('/records/{id}', [SupplementaryController::class, 'show'])->middleware('auth:sanctum');
//     Route::post('/records', [SupplementaryController::class, 'store'])->middleware('auth:sanctum');
//     Route::put('/records/{id}', [SupplementaryController::class, 'update'])->middleware('auth:sanctum');
//     Route::delete('/records/{id}', [SupplementaryController::class, 'destroy'])->middleware('auth:sanctum');
//     Route::delete('/records', [SupplementaryController::class, 'destroyMultiple'])->middleware('auth:sanctum');
//     Route::get('/filter-options', [SupplementaryController::class, 'getFilterOptions'])->middleware('auth:sanctum');
//     Route::get('/summary', [SupplementaryController::class, 'getSummary'])->middleware('auth:sanctum');
// });


// use App\Http\Controllers\API\MonthlyFincanceController;

// // Monthly Finance Routes
// Route::prefix('monthly-finance')->group(function () {
//     Route::get('/records', [MonthlyFincanceController::class, 'index'])->middleware('auth:sanctum');
//     Route::get('/records/{id}', [MonthlyFincanceController::class, 'show'])->middleware('auth:sanctum');
//     Route::post('/records', [MonthlyFincanceController::class, 'store'])->middleware('auth:sanctum');
//     Route::put('/records/{id}', [MonthlyFincanceController::class, 'update'])->middleware('auth:sanctum');
//     Route::delete('/records/{id}', [MonthlyFincanceController::class, 'destroy'])->middleware('auth:sanctum');
//     Route::delete('/records', [MonthlyFincanceController::class, 'destroyMultiple'])->middleware('auth:sanctum');
//     Route::post('/import', [MonthlyFincanceController::class, 'import'])->middleware('auth:sanctum');
//     Route::get('/filter-options', [MonthlyFincanceController::class, 'getFilterOptions'])->middleware('auth:sanctum');
//     Route::get('/summary', [MonthlyFincanceController::class, 'getSummary'])->middleware('auth:sanctum');
//     Route::get('/export', [MonthlyFincanceController::class, 'export'])->middleware('auth:sanctum');
// });

// use App\Http\Controllers\API\OpeningBalanceController;

// // Opening Balance Routes
// Route::prefix('opening-balance')->group(function () {
//     Route::get('/records', [OpeningBalanceController::class, 'index'])->middleware('auth:sanctum');
//     Route::get('/records/{id}', [OpeningBalanceController::class, 'show'])->middleware('auth:sanctum');
//     Route::post('/records', [OpeningBalanceController::class, 'store'])->middleware('auth:sanctum');
//     Route::put('/records/{id}', [OpeningBalanceController::class, 'update'])->middleware('auth:sanctum');
//     Route::delete('/records/{id}', [OpeningBalanceController::class, 'destroy'])->middleware('auth:sanctum');
//     Route::delete('/records', [OpeningBalanceController::class, 'destroyMultiple'])->middleware('auth:sanctum');
//     Route::post('/import', [OpeningBalanceController::class, 'import'])->middleware('auth:sanctum');
//     Route::get('/filter-options', [OpeningBalanceController::class, 'getFilterOptions'])->middleware('auth:sanctum');
//     Route::get('/summary', [OpeningBalanceController::class, 'getSummary'])->middleware('auth:sanctum');
//     Route::get('/export', [OpeningBalanceController::class, 'export'])->middleware('auth:sanctum');
// });

// use App\Http\Controllers\API\ImpressIssueController;

// // Impress Issue Routes
// Route::prefix('impress-issue')->group(function () {
//     Route::get('/records', [ImpressIssueController::class, 'index'])->middleware('auth:sanctum');
//     Route::get('/records/{id}', [ImpressIssueController::class, 'show'])->middleware('auth:sanctum');
//     Route::post('/records', [ImpressIssueController::class, 'store'])->middleware('auth:sanctum');
//     Route::put('/records/{id}', [ImpressIssueController::class, 'update'])->middleware('auth:sanctum');
//     Route::delete('/records/{id}', [ImpressIssueController::class, 'destroy'])->middleware('auth:sanctum');
//     Route::delete('/records', [ImpressIssueController::class, 'destroyMultiple'])->middleware('auth:sanctum');
//     Route::post('/import', [ImpressIssueController::class, 'import'])->middleware('auth:sanctum');
//     Route::get('/filter-options', [ImpressIssueController::class, 'getFilterOptions'])->middleware('auth:sanctum');
//     Route::get('/summary', [ImpressIssueController::class, 'getSummary'])->middleware('auth:sanctum');
//     Route::get('/export', [ImpressIssueController::class, 'export'])->middleware('auth:sanctum');
// });


// use App\Http\Controllers\API\ImpressSettlementController;

// // Impress Settlement Routes
// Route::prefix('impress-settlement')->group(function () {
//     Route::get('/records', [ImpressSettlementController::class, 'index'])->middleware('auth:sanctum');
//     Route::get('/records/{id}', [ImpressSettlementController::class, 'show'])->middleware('auth:sanctum');
//     Route::post('/records', [ImpressSettlementController::class, 'store'])->middleware('auth:sanctum');
//     Route::put('/records/{id}', [ImpressSettlementController::class, 'update'])->middleware('auth:sanctum');
//     Route::delete('/records/{id}', [ImpressSettlementController::class, 'destroy'])->middleware('auth:sanctum');
//     Route::delete('/records', [ImpressSettlementController::class, 'destroyMultiple'])->middleware('auth:sanctum');
//     Route::post('/import', [ImpressSettlementController::class, 'import'])->middleware('auth:sanctum');
//     Route::get('/filter-options', [ImpressSettlementController::class, 'getFilterOptions'])->middleware('auth:sanctum');
//     Route::get('/summary', [ImpressSettlementController::class, 'getSummary'])->middleware('auth:sanctum');
//     Route::get('/export', [ImpressSettlementController::class, 'export'])->middleware('auth:sanctum');
// });

// use App\Http\Controllers\API\NetExpenditureController;

// // Net Expenditure Routes
// Route::prefix('net-expenditure')->group(function () {
//     Route::get('/data', [NetExpenditureController::class, 'getData'])->middleware('auth:sanctum');
//     Route::get('/filter-options', [NetExpenditureController::class, 'getFilterOptionsEndpoint'])->middleware('auth:sanctum');
//     Route::get('/export', [NetExpenditureController::class, 'export'])->middleware('auth:sanctum');
// });



// use App\Http\Controllers\API\NetAllocationController;


// // Net Allocation Routes
// Route::prefix('net-allocation')->middleware('auth:sanctum')->group(function () {
//     // Get data with filters
//     Route::get('/data', [NetAllocationController::class, 'getData']);
    
//     // Get filter options (dropdown values)
//     Route::get('/filter-options', [NetAllocationController::class, 'getFilterOptionsEndpoint']);
    
   
// });

// use App\Http\Controllers\API\UserController;

// Route::middleware('auth:sanctum')->group(function () {
//     // User profile routes
//     Route::get('/user/profile', [UserController::class, 'getProfile']);
//     Route::put('/user/profile', [UserController::class, 'updateProfile']);
    
//     // ... other routes
// });


// use App\Http\Controllers\API\WOPController;


// // WOP Routes
// Route::prefix('wop')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [WOPController::class, 'getData']);
//     Route::get('/filter-options', [WOPController::class, 'getFilterOptions']);
//     Route::get('/export', [WOPController::class, 'export']);
// });


// use App\Http\Controllers\API\COEOWController;


// // COEOW Routes (Classification of Expenditure - Object Wise)
// Route::prefix('coeow')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [COEOWController::class, 'getData']);
//     Route::get('/filter-options', [COEOWController::class, 'getFilterOptions']);
//     Route::get('/export', [COEOWController::class, 'export']);
// });


// use App\Http\Controllers\API\COEHWController;


// // COEHW (Classification of Expenditure Head Wise) Routes
// Route::prefix('coehw')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [COEHWController::class, 'getData']);
//     Route::get('/filter-options', [COEHWController::class, 'getFilterOptions']);
//     Route::get('/export', [COEHWController::class, 'export']);
// });


// use App\Http\Controllers\API\RCExpenditureController;


// // RC Expenditure Routes
// Route::prefix('rc-expenditure')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [RCExpenditureController::class, 'getData']);
//     Route::get('/filter-options', [RCExpenditureController::class, 'getFilterOptions']);
//     Route::get('/export', [RCExpenditureController::class, 'export']);
// });

// use App\Http\Controllers\API\ODDController;

// // ODD (Other Department Debits) Routes
// Route::prefix('odd')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [ODDController::class, 'getData']);
//     Route::get('/filter-options', [ODDController::class, 'getFilterOptions']);
//     Route::get('/export', [ODDController::class, 'export']);
// });

// use App\Http\Controllers\API\ODSController;

// // ODS (Other Department Surcharge) Routes
// Route::prefix('ods')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [ODSController::class, 'getData']);
//     Route::get('/filter-options', [ODSController::class, 'getFilterOptions']);
//     Route::get('/export', [ODSController::class, 'export']);
// });


// use App\Http\Controllers\API\JournalSummaryController;


// // Journal Summary Routes
// Route::prefix('journal-summary')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [JournalSummaryController::class, 'getData']);
//     Route::get('/filter-options', [JournalSummaryController::class, 'getFilterOptions']);
//     Route::get('/export', [JournalSummaryController::class, 'export']);
// });


// use App\Http\Controllers\API\MainJournalController;


// // Main Journal Routes
// Route::prefix('main-journal')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [MainJournalController::class, 'getData']);
//     Route::get('/filter-options', [MainJournalController::class, 'getFilterOptions']);
//     Route::get('/export', [MainJournalController::class, 'export']);
// });

// use App\Http\Controllers\API\ImprestBalanceController;


// // Imprest Balance Routes
// Route::prefix('imprest-balance')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [ImprestBalanceController::class, 'getData']);
//     Route::get('/filter-options', [ImprestBalanceController::class, 'getFilterOptions']);
//     Route::get('/export', [ImprestBalanceController::class, 'export']);
// });


// use App\Http\Controllers\API\MaintenanceController;


// // Maintenance Routes
// Route::prefix('maintenance')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [MaintenanceController::class, 'getData']);
//     Route::get('/filter-options', [MaintenanceController::class, 'getFilterOptions']);
//     Route::get('/export', [MaintenanceController::class, 'export']);
// });


// use App\Http\Controllers\API\CBGController;


// // CBG (Main Ministry) Routes
// Route::prefix('cbg')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [CBGController::class, 'getData']);
//     Route::get('/filter-options', [CBGController::class, 'getFilterOptions']);
//     Route::get('/export', [CBGController::class, 'export']);
// });

// use App\Http\Controllers\API\PSDController;

// // PSD Routes
// Route::prefix('psd')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [PSDController::class, 'getData']);
//     Route::get('/filter-options', [PSDController::class, 'getFilterOptions']);
//     Route::get('/export', [PSDController::class, 'export']);
// });



// use App\Http\Controllers\API\DashboardController;


// // Dashboard Routes
// Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {
//     Route::get('/data', [DashboardController::class, 'getDashboardData']);
//     Route::get('/filter-options', [DashboardController::class, 'getFilterOptions']);
// });





use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\BudgetController;
use App\Http\Controllers\API\SupplementaryController;
use App\Http\Controllers\API\MonthlyFincanceController;
use App\Http\Controllers\API\OpeningBalanceController;
use App\Http\Controllers\API\ImpressIssueController;
use App\Http\Controllers\API\ImpressSettlementController;
use App\Http\Controllers\API\NetExpenditureController;
use App\Http\Controllers\API\NetAllocationController;
use App\Http\Controllers\API\WOPController;
use App\Http\Controllers\API\COEOWController;
use App\Http\Controllers\API\COEHWController;
use App\Http\Controllers\API\RCExpenditureController;
use App\Http\Controllers\API\ODDController;
use App\Http\Controllers\API\ODSController;
use App\Http\Controllers\API\JournalSummaryController;
use App\Http\Controllers\API\MainJournalController;
use App\Http\Controllers\API\ImprestBalanceController;
use App\Http\Controllers\API\MaintenanceController;
use App\Http\Controllers\API\CBGController;
use App\Http\Controllers\API\PSDController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\UpkeepController;
use App\Http\Controllers\API\HeadInfoController;
use App\Http\Controllers\API\ItemCodeController;
use App\Http\Controllers\API\EstimateController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// ============================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ============================================
// PROTECTED ROUTES (Authentication Required)
// ============================================
Route::middleware('auth:sanctum')->group(function () {


    // ============================================
    // AUTH ROUTES
    // ============================================
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // User Profile Routes
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'getProfile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
    });

    // Test Route
    Route::get('/hello', function () {
        return response()->json(['message' => 'API is working!']);
    });

    // ============================================
    // BUDGET ROUTES
    // ============================================
      Route::middleware(['role:admin'])->group(function () {
    Route::prefix('budget')->group(function () {
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

    // ============================================
    // SUPPLEMENTARY ROUTES
    // ============================================
    Route::prefix('supplementary')->group(function () {
        Route::get('/records', [SupplementaryController::class, 'index']);
        Route::get('/records/{id}', [SupplementaryController::class, 'show']);
        Route::post('/records', [SupplementaryController::class, 'store']);
        Route::put('/records/{id}', [SupplementaryController::class, 'update']);
        Route::delete('/records/{id}', [SupplementaryController::class, 'destroy']);
        Route::delete('/records', [SupplementaryController::class, 'destroyMultiple']);
        Route::get('/filter-options', [SupplementaryController::class, 'getFilterOptions']);
        Route::get('/summary', [SupplementaryController::class, 'getSummary']);
    });

    // ============================================
    // MONTHLY FINANCE ROUTES
    // ============================================
    Route::prefix('monthly-finance')->group(function () {
        Route::get('/records', [MonthlyFincanceController::class, 'index']);
        Route::get('/records/{id}', [MonthlyFincanceController::class, 'show']);
        Route::post('/records', [MonthlyFincanceController::class, 'store']);
        Route::put('/records/{id}', [MonthlyFincanceController::class, 'update']);
        Route::delete('/records/{id}', [MonthlyFincanceController::class, 'destroy']);
        Route::delete('/records', [MonthlyFincanceController::class, 'destroyMultiple']);
        Route::post('/import', [MonthlyFincanceController::class, 'import']);
        Route::get('/filter-options', [MonthlyFincanceController::class, 'getFilterOptions']);
        Route::get('/summary', [MonthlyFincanceController::class, 'getSummary']);
        Route::get('/export', [MonthlyFincanceController::class, 'export']);
    });

    // ============================================
    // OPENING BALANCE ROUTES
    // ============================================
    Route::prefix('opening-balance')->group(function () {
        Route::get('/records', [OpeningBalanceController::class, 'index']);
        Route::get('/records/{id}', [OpeningBalanceController::class, 'show']);
        Route::post('/records', [OpeningBalanceController::class, 'store']);
        Route::put('/records/{id}', [OpeningBalanceController::class, 'update']);
        Route::delete('/records/{id}', [OpeningBalanceController::class, 'destroy']);
        Route::delete('/records', [OpeningBalanceController::class, 'destroyMultiple']);
        Route::post('/import', [OpeningBalanceController::class, 'import']);
        Route::get('/filter-options', [OpeningBalanceController::class, 'getFilterOptions']);
        Route::get('/summary', [OpeningBalanceController::class, 'getSummary']);
        Route::get('/export', [OpeningBalanceController::class, 'export']);
    });

    // ============================================
    // IMPRESS ISSUE ROUTES
    // ============================================
    Route::prefix('impress-issue')->group(function () {
        Route::get('/records', [ImpressIssueController::class, 'index']);
        Route::get('/records/{id}', [ImpressIssueController::class, 'show']);
        Route::post('/records', [ImpressIssueController::class, 'store']);
        Route::put('/records/{id}', [ImpressIssueController::class, 'update']);
        Route::delete('/records/{id}', [ImpressIssueController::class, 'destroy']);
        Route::delete('/records', [ImpressIssueController::class, 'destroyMultiple']);
        Route::post('/import', [ImpressIssueController::class, 'import']);
        Route::get('/filter-options', [ImpressIssueController::class, 'getFilterOptions']);
        Route::get('/summary', [ImpressIssueController::class, 'getSummary']);
        Route::get('/export', [ImpressIssueController::class, 'export']);
    });

    // ============================================
    // IMPRESS SETTLEMENT ROUTES
    // ============================================
    Route::prefix('impress-settlement')->group(function () {
        Route::get('/records', [ImpressSettlementController::class, 'index']);
        Route::get('/records/{id}', [ImpressSettlementController::class, 'show']);
        Route::post('/records', [ImpressSettlementController::class, 'store']);
        Route::put('/records/{id}', [ImpressSettlementController::class, 'update']);
        Route::delete('/records/{id}', [ImpressSettlementController::class, 'destroy']);
        Route::delete('/records', [ImpressSettlementController::class, 'destroyMultiple']);
        Route::post('/import', [ImpressSettlementController::class, 'import']);
        Route::get('/filter-options', [ImpressSettlementController::class, 'getFilterOptions']);
        Route::get('/summary', [ImpressSettlementController::class, 'getSummary']);
        Route::get('/export', [ImpressSettlementController::class, 'export']);
    });

    // ============================================
    // NET EXPENDITURE ROUTES
    // ============================================
    Route::prefix('net-expenditure')->group(function () {
        Route::get('/data', [NetExpenditureController::class, 'getData']);
        Route::get('/filter-options', [NetExpenditureController::class, 'getFilterOptionsEndpoint']);
        Route::get('/export', [NetExpenditureController::class, 'export']);
    });

    // ============================================
    // NET ALLOCATION ROUTES
    // ============================================
    Route::prefix('net-allocation')->group(function () {
        Route::get('/data', [NetAllocationController::class, 'getData']);
        Route::get('/filter-options', [NetAllocationController::class, 'getFilterOptionsEndpoint']);
    });

    // ============================================
    // WOP (Work Order/Project) ROUTES
    // ============================================
    Route::prefix('wop')->group(function () {
        Route::get('/data', [WOPController::class, 'getData']);
        Route::get('/filter-options', [WOPController::class, 'getFilterOptions']);
        Route::get('/export', [WOPController::class, 'export']);
    });

    // ============================================
    // COEOW (Classification of Expenditure - Object Wise) ROUTES
    // ============================================
    Route::prefix('coeow')->group(function () {
        Route::get('/data', [COEOWController::class, 'getData']);
        Route::get('/filter-options', [COEOWController::class, 'getFilterOptions']);
        Route::get('/export', [COEOWController::class, 'export']);
    });

    // ============================================
    // COEHW (Classification of Expenditure - Head Wise) ROUTES
    // ============================================
    Route::prefix('coehw')->group(function () {
        Route::get('/data', [COEHWController::class, 'getData']);
        Route::get('/filter-options', [COEHWController::class, 'getFilterOptions']);
        Route::get('/export', [COEHWController::class, 'export']);
    });

    // ============================================
    // RC EXPENDITURE ROUTES
    // ============================================
    Route::prefix('rc-expenditure')->group(function () {
        Route::get('/data', [RCExpenditureController::class, 'getData']);
        Route::get('/filter-options', [RCExpenditureController::class, 'getFilterOptions']);
        Route::get('/export', [RCExpenditureController::class, 'export']);
    });

    // ============================================
    // ODD (Other Department Debits) ROUTES
    // ============================================
    Route::prefix('odd')->group(function () {
        Route::get('/data', [ODDController::class, 'getData']);
        Route::get('/filter-options', [ODDController::class, 'getFilterOptions']);
        Route::get('/export', [ODDController::class, 'export']);
    });

    // ============================================
    // ODS (Other Department Surcharge) ROUTES
    // ============================================
    Route::prefix('ods')->group(function () {
        Route::get('/data', [ODSController::class, 'getData']);
        Route::get('/filter-options', [ODSController::class, 'getFilterOptions']);
        Route::get('/export', [ODSController::class, 'export']);
    });

    // ============================================
    // JOURNAL SUMMARY ROUTES
    // ============================================
    Route::prefix('journal-summary')->group(function () {
        Route::get('/data', [JournalSummaryController::class, 'getData']);
        Route::get('/filter-options', [JournalSummaryController::class, 'getFilterOptions']);
        Route::get('/export', [JournalSummaryController::class, 'export']);
    });

    // ============================================
    // MAIN JOURNAL ROUTES
    // ============================================
    Route::prefix('main-journal')->group(function () {
        Route::get('/data', [MainJournalController::class, 'getData']);
        Route::get('/filter-options', [MainJournalController::class, 'getFilterOptions']);
        Route::get('/export', [MainJournalController::class, 'export']);
    });

    // ============================================
    // IMPREST BALANCE ROUTES
    // ============================================
    Route::prefix('imprest-balance')->group(function () {
        Route::get('/data', [ImprestBalanceController::class, 'getData']);
        Route::get('/filter-options', [ImprestBalanceController::class, 'getFilterOptions']);
        Route::get('/export', [ImprestBalanceController::class, 'export']);
    });

    // ============================================
    // MAINTENANCE ROUTES
    // ============================================
    Route::prefix('maintenance')->group(function () {
        Route::get('/data', [MaintenanceController::class, 'getData']);
        Route::get('/filter-options', [MaintenanceController::class, 'getFilterOptions']);
        Route::get('/export', [MaintenanceController::class, 'export']);
    });

    // ============================================
    // CBG ROUTES
    // ============================================
    Route::prefix('cbg')->group(function () {
        Route::get('/data', [CBGController::class, 'getData']);
        Route::get('/filter-options', [CBGController::class, 'getFilterOptions']);
        Route::get('/export', [CBGController::class, 'export']);
    });

    // ============================================
    // PSD ROUTES
    // ============================================
    Route::prefix('psd')->group(function () {
        Route::get('/data', [PSDController::class, 'getData']);
        Route::get('/filter-options', [PSDController::class, 'getFilterOptions']);
        Route::get('/export', [PSDController::class, 'export']);
    });

    // ============================================
    // DASHBOARD ROUTES
    // ============================================
    Route::prefix('dashboard')->group(function () {
        Route::get('/data', [DashboardController::class, 'getDashboardData']);
        Route::get('/filter-options', [DashboardController::class, 'getFilterOptions']);
    });

   


// Upkeep Routes
Route::prefix('upkeep')->group(function () {
    Route::get('/data', [UpkeepController::class, 'getData']);
    Route::get('/filter-options', [UpkeepController::class, 'getFilterOptions']);
    Route::get('/export', [UpkeepController::class, 'export']);
});

    
// Head Info routes
Route::prefix('head-info')->group(function () {
    Route::get('/', [HeadInfoController::class, 'index']);              // Get all heads
    Route::get('/list', [HeadInfoController::class, 'getHeadsList']);   // Get heads for dropdown
    Route::get('/search', [HeadInfoController::class, 'search']);       // Search heads
    Route::get('/{head}', [HeadInfoController::class, 'show']);         // Get single head
    Route::post('/', [HeadInfoController::class, 'store']);             // Create head
    Route::put('/{head}', [HeadInfoController::class, 'update']);       // Update head
    Route::delete('/{head}', [HeadInfoController::class, 'destroy']);   // Delete head
    Route::delete('/delete-multiple', [HeadInfoController::class, 'destroyMultiple']); // Delete multiple
});


    // Item Code routes
Route::prefix('item-code')->group(function () {
    Route::get('/', [ItemCodeController::class, 'index']);              // Get all items
    Route::get('/list', [ItemCodeController::class, 'getItemsList']);   // Get items for dropdown
    Route::get('/filter-options', [ItemCodeController::class, 'getFilterOptions']); // Filter options
    Route::get('/years-summary', [ItemCodeController::class, 'getYearsSummary']); // Years summary
    Route::get('/search', [ItemCodeController::class, 'search']);       // Search items
    Route::get('/{item}', [ItemCodeController::class, 'show']);         // Get single item
    Route::post('/', [ItemCodeController::class, 'store']);             // Create item
    Route::put('/{item}', [ItemCodeController::class, 'update']);       // Update item
    Route::delete('/{item}', [ItemCodeController::class, 'destroy']);   // Delete item
    Route::delete('/delete-multiple', [ItemCodeController::class, 'destroyMultiple']); // Delete multiple
});


Route::prefix('estimates')->group(function () {
    Route::get('/', [EstimateController::class, 'index']);
    Route::get('/filter-options', [EstimateController::class, 'getFilterOptions']);
    Route::get('/summary', [EstimateController::class, 'getSummary']);
    Route::get('/export', [EstimateController::class, 'export']);
    Route::get('/{id}', [EstimateController::class, 'show']);
    Route::post('/', [EstimateController::class, 'store']);
    Route::post('/import', [EstimateController::class, 'import']);
    Route::put('/{id}', [EstimateController::class, 'update']);
    Route::delete('/{id}', [EstimateController::class, 'destroy']);
    Route::delete('/delete-multiple', [EstimateController::class, 'destroyMultiple']);
});

 });
}); // End of auth:sanctum middleware group