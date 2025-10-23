<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Calculate contract balance (rental total - rental payments only)
     */
    private function calculateContractBalance($contract): float
    {
        // Base rental amount only (exclude additional fees from balance calculation)
        $totalAmount = (float) $contract->total_amount;
        
        // Calculate paid amount from rental-related allocations only (exclude additional fees)
        $paidAmount = (float) \App\Models\PaymentReceiptAllocation::query()
            ->whereHas('paymentReceipt', function ($q) use ($contract) {
                $q->where('contract_id', $contract->id)
                  ->where('status', 'completed');
            })
            ->whereNotIn('row_id', function ($query) {
                $query->select('id')
                      ->from('contract_additional_fees')
                      ->whereColumn('contract_additional_fees.id', 'payment_receipt_allocations.row_id');
            })
            ->where('row_id', 'not like', 'additional_fee_%')
            ->sum('amount');
        
        return $totalAmount - $paidAmount;
    }

    /**
     * Calculate contract balance including additional fees (total amount + additional fees - all payments)
     */
    private function calculateContractBalanceWithAdditionalFees($contract): float
    {
        // Base rental amount
        $rentalAmount = (float) $contract->total_amount;
        
        // Calculate additional fees total
        $additionalFeesTotal = (float) $contract->additionalFees()->sum('total');
        
        // Total amount including additional fees
        $totalAmount = $rentalAmount + $additionalFeesTotal;
        
        // Calculate paid amount from ALL allocations (rental + additional fees)
        $paidAmount = (float) \App\Models\PaymentReceiptAllocation::query()
            ->whereHas('paymentReceipt', function ($q) use ($contract) {
                $q->where('contract_id', $contract->id)
                  ->where('status', 'completed');
            })
            ->sum('amount');
        
        return $totalAmount - $paidAmount;
    }

    public function index()
    {
        $lateInvoices = Invoice::where('due_date', '<', now())
            ->where('remaining_amount', '>', 0);
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

        // Vehicles to deliver today (contracts starting today)
        $vehiclesToDeliverToday = Contract::where('status', 'active')
            ->whereDate('start_date', today())
            ->with(['customer', 'vehicle'])
            ->orderBy('start_date')
            ->get();

        // Vehicles to receive (contracts ending today and tomorrow)
        $vehiclesToReceive = Contract::where('status', 'active')
            ->where(function($query) {
                $query->whereDate('end_date', today())
                      ->orWhereDate('end_date', Carbon::tomorrow());
            })
            ->with(['customer', 'vehicle'])
            ->orderBy('end_date')
            ->get();

        // Upcoming reservations in the next 24 hours
        $upcomingReservations = Reservation::whereIn('status', ['pending', 'confirmed'])
            ->where('pickup_date', '>=', now())
            ->where('pickup_date', '<=', now()->addHours(24))
            ->with(['customer', 'vehicle'])
            ->orderBy('pickup_date')
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
                    'status' => $invoice->payment_status,
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
            'vehicles_to_deliver_today' => $vehiclesToDeliverToday->map(function($contract) {
                // Calculate balance FIRST with original amounts
                $balance = $this->calculateContractBalanceWithAdditionalFees($contract);
                
                // THEN calculate additional fees total for display
                $additionalFeesTotal = (float) $contract->additionalFees()->sum('total');
                
                return [
                    'id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'start_date' => $contract->start_date,
                    'total_amount' => (float) $contract->total_amount + $additionalFeesTotal,
                    'balance' => $balance,
                    'customer' => $contract->customer ? [
                        'id' => $contract->customer->id,
                        'first_name' => $contract->customer->first_name,
                        'last_name' => $contract->customer->last_name,
                        'business_name' => $contract->customer->business_name,
                    ] : null,
                    'vehicle' => $contract->vehicle ? [
                        'id' => $contract->vehicle->id,
                        'plate_number' => $contract->vehicle->plate_number,
                        'make' => $contract->vehicle->make,
                        'model' => $contract->vehicle->model,
                        'year' => $contract->vehicle->year,
                    ] : null,
                ];
            }),
            'vehicles_to_receive' => $vehiclesToReceive->map(function($contract) {
                // Calculate balance FIRST with original amounts
                $balance = $this->calculateContractBalanceWithAdditionalFees($contract);
                
                // THEN calculate additional fees total for display
                $additionalFeesTotal = (float) $contract->additionalFees()->sum('total');
                
                return [
                    'id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'end_date' => $contract->end_date,
                    'total_amount' => (float) $contract->total_amount + $additionalFeesTotal,
                    'balance' => $balance,
                    'customer' => $contract->customer ? [
                        'id' => $contract->customer->id,
                        'first_name' => $contract->customer->first_name,
                        'last_name' => $contract->customer->last_name,
                        'business_name' => $contract->customer->business_name,
                    ] : null,
                    'vehicle' => $contract->vehicle ? [
                        'id' => $contract->vehicle->id,
                        'plate_number' => $contract->vehicle->plate_number,
                        'make' => $contract->vehicle->make,
                        'model' => $contract->vehicle->model,
                        'year' => $contract->vehicle->year,
                    ] : null,
                ];
            }),
            'upcoming_reservations' => $upcomingReservations->map(function($reservation) {
                return [
                    'id' => $reservation->id,
                    'uid' => $reservation->uid,
                    'pickup_date' => $reservation->pickup_date,
                    'return_date' => $reservation->return_date,
                    'status' => $reservation->status,
                    'total_amount' => $reservation->total_amount,
                    'duration_days' => $reservation->duration_days,
                    'customer' => $reservation->customer ? [
                        'id' => $reservation->customer->id,
                        'first_name' => $reservation->customer->first_name,
                        'last_name' => $reservation->customer->last_name,
                        'business_name' => $reservation->customer->business_name,
                    ] : null,
                    'vehicle' => $reservation->vehicle ? [
                        'id' => $reservation->vehicle->id,
                        'plate_number' => $reservation->vehicle->plate_number,
                        'make' => $reservation->vehicle->make,
                        'model' => $reservation->vehicle->model,
                        'year' => $reservation->vehicle->year,
                    ] : null,
                ];
            }),
        ]);
    }
}
