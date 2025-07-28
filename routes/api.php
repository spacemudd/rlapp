<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReservationApiController;
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
    });

    // Add login route
    Route::post('/login', [\App\Http\Controllers\Api\AuthApiController::class, 'login']);
});

// Search endpoints moved to web routes for proper session authentication

Route::middleware(['api.key'])->group(function () {
    // Vehicle API endpoints (requires API key)
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleApiController::class, 'index'])->name('api.vehicles.index');
        Route::get('/{id}', [VehicleApiController::class, 'show'])->name('api.vehicles.show');
    });
});
