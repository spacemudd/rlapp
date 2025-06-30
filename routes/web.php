<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

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
    Route::patch('/contracts/{contract}/void', [App\Http\Controllers\ContractController::class, 'void'])->name('contracts.void');
    Route::post('/contracts/{contract}/create-invoice', [App\Http\Controllers\ContractController::class, 'createInvoice'])->name('contracts.create-invoice');
    Route::get('/contracts/{contract}/pdf', [App\Http\Controllers\ContractController::class, 'downloadPdf'])->name('contracts.pdf');

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
    Route::get('/api/customers/search', [App\Http\Controllers\ContractController::class, 'searchCustomers'])->name('api.customers.search');
    Route::get('/api/vehicles/search', [App\Http\Controllers\ContractController::class, 'searchVehicles'])->name('api.vehicles.search');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('locations', App\Http\Controllers\LocationController::class);
    Route::get('/api/locations', [App\Http\Controllers\LocationController::class, 'api'])->name('api.locations');
});

Route::resource('customers', App\Http\Controllers\CustomerController::class)
    ->except(['show', 'create', 'edit'])
    ->middleware(['auth', 'verified']);



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


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
