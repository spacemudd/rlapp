<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReservationApiController;
use App\Http\Controllers\Api\TestReservationApiController;
use App\Http\Controllers\Api\CustomerApiController;

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
        Route::get('/available-vehicles', [ReservationApiController::class, 'availableVehicles']);
        Route::get('/search', [ReservationApiController::class, 'search']);
        Route::get('/status/{status}', [ReservationApiController::class, 'byStatus']);
        Route::get('/{id}', [ReservationApiController::class, 'show']);
        Route::put('/{id}', [ReservationApiController::class, 'update']);
        Route::delete('/{id}', [ReservationApiController::class, 'destroy']);
        Route::patch('/{id}/status', [ReservationApiController::class, 'updateStatus']);
    });

    // Test API endpoints (بدون authentication للاختبار)
    Route::prefix('test')->group(function () {
        Route::get('/data', [TestReservationApiController::class, 'testData']);
        Route::get('/reservations', [TestReservationApiController::class, 'index']);
        Route::post('/reservations', [TestReservationApiController::class, 'store']);
        Route::post('/custom-reservation', [TestReservationApiController::class, 'createCustom']);
        Route::post('/customers', [CustomerApiController::class, 'store']);
    });
});
