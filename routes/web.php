<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\SalikApiController;
use App\Http\Controllers\TrafficViolationsController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.generatePdf');
    Route::post('/payments/{invoice}', [\App\Http\Controllers\PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{id}/receipt', [PaymentController::class, 'downloadReceipt'])->name('payments.receipt');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'sendToCustomer'])->name('invoices.send');
    Route::get('/invoices/{invoice}/public-pdf', [InvoiceController::class, 'getPublicPdfLink'])->name('invoices.publicPdf');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('vehicles', App\Http\Controllers\VehicleController::class);
    Route::patch('/vehicles/{vehicle}/disable', [App\Http\Controllers\VehicleController::class, 'disable'])->name('vehicles.disable');
    Route::patch('/vehicles/{vehicle}/enable', [App\Http\Controllers\VehicleController::class, 'enable'])->name('vehicles.enable');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('contracts', App\Http\Controllers\ContractController::class);
    Route::patch('/contracts/{contract}/activate', [App\Http\Controllers\ContractController::class, 'activate'])->name('contracts.activate');
    Route::patch('/contracts/{contract}/complete', [App\Http\Controllers\ContractController::class, 'complete'])->name('contracts.complete');
    Route::post('/contracts/{contract}/record-return', [App\Http\Controllers\ContractController::class, 'recordReturn'])->name('contracts.record-return');
    Route::patch('/contracts/{contract}/void', [App\Http\Controllers\ContractController::class, 'void'])->name('contracts.void');
    Route::post('/contracts/{contract}/create-invoice', [App\Http\Controllers\ContractController::class, 'createInvoice'])->name('contracts.create-invoice');
    Route::get('/contracts/{contract}/pdf', [App\Http\Controllers\ContractController::class, 'downloadPdf'])->name('contracts.pdf');
    Route::post('/contracts/{contract}/extend', [App\Http\Controllers\ContractController::class, 'extend'])->name('contracts.extend');
    Route::get('/contracts/{contract}/extension-pricing', [App\Http\Controllers\ContractController::class, 'calculateExtensionPricing'])->name('contracts.extension-pricing');
    Route::get('/contracts/{contract}/finalize', [App\Http\Controllers\ContractController::class, 'showFinalize'])->name('contracts.finalize');
    Route::post('/contracts/{contract}/finalize', [App\Http\Controllers\ContractController::class, 'finalize'])->name('contracts.finalize');

    // Test routes
    Route::get('/test-arabic', function() {
        return view('contracts.test-arabic');
    });

    Route::get('/test-arabic-pdf', function() {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('contracts.test-arabic');

        // Configure PDF options for Arabic support
        $pdf->getDomPDF()->getOptions()->set('fontDir', storage_path('fonts/'));
        $pdf->getDomPDF()->getOptions()->set('fontCache', storage_path('fonts/'));
        $pdf->getDomPDF()->getOptions()->set('isRemoteEnabled', true);
        $pdf->getDomPDF()->getOptions()->set('defaultFont', 'Arial');

        return $pdf->stream('arabic-test.pdf');
    });

    // Search endpoints for async dropdowns
    Route::get('/api/pricing/calculate', [App\Http\Controllers\ContractController::class, 'calculatePricing'])->name('api.pricing.calculate');
    Route::get('/api/customers/search', [App\Http\Controllers\ContractController::class, 'searchCustomers'])->name('api.customers.search');
    Route::get('/api/vehicle-search', [App\Http\Controllers\ContractController::class, 'searchVehicles'])->name('api.vehicles.search');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('locations', App\Http\Controllers\LocationController::class);
    Route::get('/api/locations', [App\Http\Controllers\LocationController::class, 'api'])->name('api.locations');
});

Route::resource('customers', App\Http\Controllers\CustomerController::class)
    ->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/customers/{customer}/block', [App\Http\Controllers\CustomerController::class, 'block'])->name('customers.block');
    Route::post('/customers/{customer}/unblock', [App\Http\Controllers\CustomerController::class, 'unblock'])->name('customers.unblock');
    Route::get('/customers/{customer}/block-history', [App\Http\Controllers\CustomerController::class, 'blockHistory'])->name('customers.block-history');
});



Route::get('invoices/{id}/pdf', [App\Http\Controllers\InvoiceController::class, 'downloadPdf'])
    ->middleware(['auth', 'verified'])
    ->name('invoices.pdf');



// Team Management Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('team', [App\Http\Controllers\TeamManagementController::class, 'index'])->name('team.index');
    Route::patch('team/users/{user}/role', [App\Http\Controllers\TeamManagementController::class, 'updateUserRole'])->name('team.users.role');
    Route::post('team/invitations', [App\Http\Controllers\TeamManagementController::class, 'sendInvitation'])->name('team.invitations.send');
    Route::delete('team/invitations/{invitation}', [App\Http\Controllers\TeamManagementController::class, 'cancelInvitation'])->name('team.invitations.cancel');
    Route::delete('team/users/{user}', [App\Http\Controllers\TeamManagementController::class, 'removeUser'])->name('team.users.remove');
});

// Invitation Routes (public)
Route::get('invitation/{token}', [App\Http\Controllers\InvitationController::class, 'show'])->name('invitation.show');
Route::post('invitation/{token}/accept', [App\Http\Controllers\InvitationController::class, 'accept'])->name('invitation.accept');
Route::post('invitation/{token}/decline', [App\Http\Controllers\InvitationController::class, 'decline'])->name('invitation.decline');

Route::middleware(['auth', 'verified'])->group(function () {
    // Reservations Routes
    Route::resource('reservations', \App\Http\Controllers\ReservationController::class);
    Route::patch('reservations/{reservation}/status', [\App\Http\Controllers\ReservationController::class, 'updateStatus'])->name('reservations.update-status');

    // API Routes for Reservations
    Route::prefix('api/reservations')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ReservationApiController::class, 'index'])->name('api.reservations.index');
        Route::post('/', [\App\Http\Controllers\Api\ReservationApiController::class, 'store'])->name('api.reservations.store');
        Route::get('/statistics', [\App\Http\Controllers\Api\ReservationApiController::class, 'statistics'])->name('api.reservations.statistics');
        Route::get('/today', [\App\Http\Controllers\Api\ReservationApiController::class, 'today'])->name('api.reservations.today');
        Route::get('/tomorrow', [\App\Http\Controllers\Api\ReservationApiController::class, 'tomorrow'])->name('api.reservations.tomorrow');
        Route::get('/pending', [\App\Http\Controllers\Api\ReservationApiController::class, 'pending'])->name('api.reservations.pending');
        Route::get('/available-vehicles', [\App\Http\Controllers\Api\ReservationApiController::class, 'availableVehicles'])->name('api.reservations.available-vehicles');
        Route::get('/search', [\App\Http\Controllers\Api\ReservationApiController::class, 'search'])->name('api.reservations.search');
        Route::get('/status/{status}', [\App\Http\Controllers\Api\ReservationApiController::class, 'byStatus'])->name('api.reservations.by-status');
        Route::get('/{id}', [\App\Http\Controllers\Api\ReservationApiController::class, 'show'])->name('api.reservations.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\ReservationApiController::class, 'update'])->name('api.reservations.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\ReservationApiController::class, 'destroy'])->name('api.reservations.destroy');
        Route::patch('/{id}/status', [\App\Http\Controllers\Api\ReservationApiController::class, 'updateStatus'])->name('api.reservations.update-status');
        Route::patch('/{id}/change-status', [\App\Http\Controllers\Api\ReservationApiController::class, 'changeStatus'])->name('api.reservations.change-status');
    });

    // Route للحصول على CSRF Token للاختبار (بدون middleware)
    Route::get('/api/csrf-token', function () {
        return response()->json([
            'csrf_token' => csrf_token(),
            'message' => 'Use this token in X-CSRF-TOKEN header',
            'timestamp' => now()->toISOString()
        ]);
    })->name('api.csrf-token')->withoutMiddleware(['auth', 'verified']);

    // API Route للاختبار بدون CSRF (للاختبار فقط)
    Route::post('/api/test/reservations', [\App\Http\Controllers\Api\ReservationApiController::class, 'store'])
        ->name('api.test.reservations.store')
        ->withoutMiddleware(['web']);

    Route::get('/fines', [\App\Http\Controllers\FineController::class, 'index'])->name('fines');
    Route::post('/fines/sync', [\App\Http\Controllers\FineController::class, 'runScript'])->name('fines.sync');
Route::post('/run-script', [App\Http\Controllers\ScriptController::class, 'run']);
Route::get('/script-log', [App\Http\Controllers\ScriptController::class, 'log']);

// Routes للـ FineController الجديد
Route::post('/fines/run-script', [App\Http\Controllers\FineController::class, 'runScript'])->name('fines.run-script');
Route::get('/fines/last-sync', [App\Http\Controllers\FineController::class, 'getLastSync'])->name('fines.last-sync');

// Route لإرجاع نسبة التقدم في ملف progress.txt
Route::get('/sync-progress', function () {
    $progressFile = base_path('scripts/progress.txt');
    $percent = 0;
    if (file_exists($progressFile)) {
        $percent = (int)file_get_contents($progressFile);
    }
    return Response::json(['progress' => $percent]);
});

Route::get('/traffic-violations', function () {
    return inertia('TrafficViolations');
});

Route::get('/traffic-violations/salik', function () {
    return inertia('TrafficViolations/Salik');
});

Route::get('/api/salik-balance', function () {
    $file = base_path('scripts/salik_balance.txt');
    $balance = file_exists($file) ? trim(file_get_contents($file)) : null;
    return response()->json(['balance' => $balance]);
});

// API route to return Salik trips data
Route::get('/api/salik-trips', function () {
    $path = base_path('scripts/salik_trips.json');
    if (file_exists($path)) {
        return response()->json(json_decode(file_get_contents($path)));
    }
    return response()->json([]);
});

Route::get('/script-status', function () {
    $path = storage_path('logs/scrap_rta.done');
    $done = file_exists($path) ? trim(file_get_contents($path)) : null;
    return response()->json(['done' => $done]);
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
