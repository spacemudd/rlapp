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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
