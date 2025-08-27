<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\Payment;
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
        $overdueContractsCount = Contract::where('status', 'active')
            ->where('end_date', '<', now())
            ->count();
        $availableCars = Vehicle::where('status', 'available')->count();
        $rentedCars = Vehicle::where('status', 'rented')->count();
        $totalCars = Vehicle::count();
        $usersCount = User::count();

        // Cash payment statistics
        $cashPayments = Payment::where('payment_method', 'cash')
            ->where('status', 'completed')
            ->where('transaction_type', 'payment');
        $cashPaymentsTotal = $cashPayments->sum('amount');
        $cashPaymentsCount = $cashPayments->count();

        // Credit card payment statistics
        $creditCardPayments = Payment::where('payment_method', 'credit_card')
            ->where('status', 'completed')
            ->where('transaction_type', 'payment');
        $creditCardPaymentsTotal = $creditCardPayments->sum('amount');
        $creditCardPaymentsCount = $creditCardPayments->count();

        // Bank transfer payment statistics
        $bankTransferPayments = Payment::where('payment_method', 'bank_transfer')
            ->where('status', 'completed')
            ->where('transaction_type', 'payment');
        $bankTransferPaymentsTotal = $bankTransferPayments->sum('amount');
        $bankTransferPaymentsCount = $bankTransferPayments->count();

        // Latest payments
        $latestPayments = Payment::where('status', 'completed')
            ->where('transaction_type', 'payment')
            ->with(['invoice', 'customer'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'late_invoices' => $lateInvoicesCount,
                'late_invoices_amount' => $lateInvoicesAmount,
                'contracts' => $contractsCount,
                'overdue_contracts' => $overdueContractsCount,
                'available_cars' => $availableCars,
                'rented_cars' => $rentedCars,
                'total_cars' => $totalCars,
                'users' => $usersCount,
                'cash_payments_total' => $cashPaymentsTotal,
                'cash_payments_count' => $cashPaymentsCount,
                'credit_card_payments_total' => $creditCardPaymentsTotal,
                'credit_card_payments_count' => $creditCardPaymentsCount,
                'bank_transfer_payments_total' => $bankTransferPaymentsTotal,
                'bank_transfer_payments_count' => $bankTransferPaymentsCount,
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
            'latest_payments' => $latestPayments->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                    'invoice' => $payment->invoice ? [
                        'id' => $payment->invoice->id,
                        'invoice_number' => $payment->invoice->invoice_number,
                    ] : null,
                    'customer' => $payment->customer ? [
                        'id' => $payment->customer->id,
                        'first_name' => $payment->customer->first_name,
                        'last_name' => $payment->customer->last_name,
                        'business_name' => $payment->customer->business_name,
                    ] : null,
                ];
            }),
        ]);
    }
}
