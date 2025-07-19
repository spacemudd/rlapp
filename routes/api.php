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

Route::middleware(['api.key'])->group(function () {
    // Vehicle API endpoints
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleApiController::class, 'index'])->name('api.vehicles.index');
        Route::get('/{id}', [VehicleApiController::class, 'show'])->name('api.vehicles.show');
    });
}); 