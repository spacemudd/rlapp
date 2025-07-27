<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReservationApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\VehicleApiController;
use App\Http\Controllers\Api\AuthApiController;

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
    // Authentication routes
    Route::post('/login', [AuthApiController::class, 'login']);
});

// Protected API Routes (مع API key authentication)
Route::middleware(['api.key'])->group(function () {

    // ========================================
    // RESERVATIONS API
    // ========================================
    Route::prefix('v1/reservations')->group(function () {
        // Get all reservations with filtering and pagination
        Route::get('/', [ReservationApiController::class, 'index']);

        // Get reservations by status
        Route::get('/status/{status}', [ReservationApiController::class, 'byStatus']);

        // Get specific reservation
        Route::get('/{id}', [ReservationApiController::class, 'show']);

        // Create new reservation
        Route::post('/', [ReservationApiController::class, 'store']);

        // Update reservation
        Route::put('/{id}', [ReservationApiController::class, 'update']);

        // Delete reservation
        Route::delete('/{id}', [ReservationApiController::class, 'destroy']);

        // Update reservation status only
        Route::patch('/{id}/status', [ReservationApiController::class, 'updateStatus']);

        // Statistics and reports
        Route::get('/statistics', [ReservationApiController::class, 'statistics']);
        Route::get('/today', [ReservationApiController::class, 'today']);
        Route::get('/tomorrow', [ReservationApiController::class, 'tomorrow']);

        // Search and filters
        Route::get('/search', [ReservationApiController::class, 'search']);
        Route::get('/available-vehicles', [ReservationApiController::class, 'availableVehicles']);
    });

    // ========================================
    // VEHICLES API
    // ========================================
    Route::prefix('v1/vehicles')->group(function () {
        // Get all vehicles
        Route::get('/', [VehicleApiController::class, 'index']);

        // Get specific vehicle
        Route::get('/{id}', [VehicleApiController::class, 'show']);

        // Create new vehicle
        Route::post('/', [VehicleApiController::class, 'store']);

        // Update vehicle
        Route::put('/{id}', [VehicleApiController::class, 'update']);

        // Delete vehicle
        Route::delete('/{id}', [VehicleApiController::class, 'destroy']);

        // Search vehicles
        Route::get('/search', [VehicleApiController::class, 'search']);

        // Get available vehicles
        Route::get('/available', [VehicleApiController::class, 'available']);
    });

    // ========================================
    // CUSTOMERS API
    // ========================================
    Route::prefix('v1/customers')->group(function () {
        // Get all customers
        Route::get('/', [CustomerApiController::class, 'index']);

        // Get specific customer
        Route::get('/{id}', [CustomerApiController::class, 'show']);

        // Create new customer
        Route::post('/', [CustomerApiController::class, 'store']);

        // Update customer
        Route::put('/{id}', [CustomerApiController::class, 'update']);

        // Delete customer
        Route::delete('/{id}', [CustomerApiController::class, 'destroy']);

        // Search customers
        Route::get('/search', [CustomerApiController::class, 'search']);

        // Get customer reservations
        Route::get('/{id}/reservations', [CustomerApiController::class, 'reservations']);
    });
});
