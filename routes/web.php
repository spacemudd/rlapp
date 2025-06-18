<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.generatePdf');
    Route::post('/payments/{invoice}', [\App\Http\Controllers\PaymentController::class, 'store'])->name('payments.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('vehicles', App\Http\Controllers\VehicleController::class);
    Route::patch('/vehicles/{vehicle}/disable', [App\Http\Controllers\VehicleController::class, 'disable'])->name('vehicles.disable');
    Route::patch('/vehicles/{vehicle}/enable', [App\Http\Controllers\VehicleController::class, 'enable'])->name('vehicles.enable');
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
