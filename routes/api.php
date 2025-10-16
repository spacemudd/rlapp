<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReservationApiController;
use App\Http\Controllers\Api\ReservationApiController as ReservationApiControllerAlias;
use App\Http\Controllers\Api\TestReservationApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\VehicleApiController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public API Routes (بدون CSRF protection)
Route::prefix('v1')->group(function () {
    // Reservations API
    Route::prefix('reservations')->group(function () {
        Route::get('/', [ReservationApiController::class, 'index']);
        Route::post('/', [ReservationApiController::class, 'store']);
        Route::get('/statistics', [ReservationApiController::class, 'statistics']);
        Route::get('/today', [ReservationApiController::class, 'today']);
        Route::get('/tomorrow', [ReservationApiController::class, 'tomorrow']);
        Route::get('/pending', [ReservationApiController::class, 'pending']);
        Route::get('/available-vehicles', [ReservationApiController::class, 'availableVehicles']);
        Route::get('/search', [ReservationApiController::class, 'search']);
        Route::get('/status/{status}', [ReservationApiController::class, 'byStatus']);
        Route::get('/{id}', [ReservationApiController::class, 'show']);
        Route::put('/{id}', [ReservationApiController::class, 'update']);
        Route::delete('/{id}', [ReservationApiController::class, 'destroy']);
        Route::patch('/{id}/status', [ReservationApiController::class, 'updateStatus']);
        Route::patch('/{id}/change-status', [ReservationApiController::class, 'changeStatus']);
    });

    // Test API endpoints (بدون authentication للاختبار)
    Route::prefix('test')->group(function () {
        Route::get('/data', [TestReservationApiController::class, 'testData']);
        Route::get('/ids', [TestReservationApiController::class, 'getAvailableIds']);
        Route::get('/reservations', [TestReservationApiController::class, 'index']);
        Route::post('/reservations', [TestReservationApiController::class, 'store']);
        Route::post('/custom-reservation', [TestReservationApiController::class, 'createCustom']);
        Route::post('/customers', [CustomerApiController::class, 'store']);

        // Test endpoints للحجوزات المعلقة بدون authentication
        Route::get('/pending-reservations', [ReservationApiController::class, 'pending'])->withoutMiddleware(['auth:sanctum']);
        Route::get('/reservations-by-status/{status}', [ReservationApiController::class, 'byStatus'])->withoutMiddleware(['auth:sanctum']);
    });

    // Add login route
    Route::post('/login', [\App\Http\Controllers\Api\AuthApiController::class, 'login']);
});

// Search endpoints moved to web routes for proper session authentication

// Vehicle availability endpoints moved to web routes to avoid API key conflicts

// System Settings and Branch info endpoints
// Using web middleware for session-based authentication
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/system-settings/fee-types', function () {
        $feeTypes = \App\Models\SystemSetting::getFeeTypes();
        return response()->json(['fee_types' => $feeTypes]);
    });
    
    Route::get('/branches/{branch}/vat-account', function (\App\Models\Branch $branch) {
        $vatAccount = null;
        if ($branch->ifrs_vat_account_id) {
            $vatAccount = \IFRS\Models\Account::find($branch->ifrs_vat_account_id);
            if ($vatAccount) {
                $vatAccount = [
                    'id' => $vatAccount->id,
                    'name' => $vatAccount->name,
                    'code' => $vatAccount->code,
                ];
            }
        }
        return response()->json(['vat_account' => $vatAccount]);
    });
});

Route::middleware(['api.key'])->group(function () {
    // Vehicle API endpoints (requires API key)
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleApiController::class, 'index'])->name('api.vehicles.index');
        Route::get('/{id}', [VehicleApiController::class, 'show'])->name('api.vehicles.show');
    });

    // Test API Key endpoint
    Route::get('/v1/test-api-key', function() {
        return response()->json([
            'success' => true,
            'message' => 'API Key is valid!'
        ]);
    });

    // Update reservation status by UID (requires API key)
    Route::patch('/v1/reservations/by-uid/{uid}/status', [ReservationApiControllerAlias::class, 'updateStatusByUid'])
        ->name('api.v1.reservations.update-status-by-uid');
});
