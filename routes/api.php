<?php

use App\Http\Controllers\Api\VehicleApiController;
use Illuminate\Support\Facades\Route;

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

// Search endpoints (requires web authentication but no API key - used by frontend)
Route::middleware(['auth:web'])->group(function () {
    Route::prefix('vehicles')->group(function () {
        Route::get('/search', [App\Http\Controllers\ContractController::class, 'searchVehicles'])->name('api.vehicles.search');
    });

    Route::prefix('customers')->group(function () {
        Route::get('/search', [App\Http\Controllers\ContractController::class, 'searchCustomers'])->name('api.customers.search');
    });
});

Route::middleware(['api.key'])->group(function () {
    // Vehicle API endpoints (requires API key)
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleApiController::class, 'index'])->name('api.vehicles.index');
        Route::get('/{id}', [VehicleApiController::class, 'show'])->name('api.vehicles.show');
    });
}); 