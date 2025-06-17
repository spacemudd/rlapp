<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::resource('customers', App\Http\Controllers\CustomerController::class)
    ->except(['show', 'create', 'edit'])
    ->middleware(['auth', 'verified']);

Route::get('invoices', function () {
    return Inertia::render('Invoices/Index');
})->middleware(['auth', 'verified'])->name('invoices');

Route::get('invoices/create', function () {
    return Inertia::render('Invoices/Create', [
        'customers' => \App\Models\Customer::select('id', 'first_name', 'last_name', 'email')
            ->where('team_id', request()->user()->team_id)
            ->where('status', 'active')
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'email' => $customer->email
                ];
            }),
        'vehicles' => \App\Models\Vehicle::select('id', 'plate_number', 'make', 'model', 'year')
            ->where('status', 'available')
            ->get()
            ->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'name' => "{$vehicle->year} {$vehicle->make} {$vehicle->model} - {$vehicle->plate_number}"
                ];
            })
    ]);
})->middleware(['auth', 'verified'])->name('invoices.create');

Route::post('invoices', [InvoiceController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('invoices.store');

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
