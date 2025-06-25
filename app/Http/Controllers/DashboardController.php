<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        $lateInvoices = Invoice::whereIn('status', ['unpaid', 'partial_paid'])
            ->where('due_date', '<', now());
        $lateInvoicesList = $lateInvoices->get();
        Log::info('Late invoices:', $lateInvoicesList->toArray());
        $lateInvoicesCount = $lateInvoicesList->count();
        $lateInvoicesAmount = $lateInvoicesList->sum('total_amount');

        $contractsCount = Contract::count();
        $availableCars = Vehicle::where('status', 'available')->count();
        $rentedCars = Vehicle::where('status', 'rented')->count();
        $totalCars = Vehicle::count();
        $usersCount = User::count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'late_invoices' => $lateInvoicesCount,
                'late_invoices_amount' => $lateInvoicesAmount,
                'contracts' => $contractsCount,
                'available_cars' => $availableCars,
                'rented_cars' => $rentedCars,
                'total_cars' => $totalCars,
                'users' => $usersCount,
            ],
            'late_invoices_list' => $lateInvoicesList->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'due_date' => $invoice->due_date,
                    'total_amount' => $invoice->total_amount,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status,
                ];
            }),
        ]);
    }
}
