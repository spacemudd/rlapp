<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AccountingService;
use Exception;

class PaymentController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function store(Request $request, $invoiceId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_date' => 'required|date',
            'status' => 'required|in:completed,pending,failed',
            'notes' => 'nullable|string',
            'transaction_type' => 'required|in:payment,deposit,refund',
            'bank_id' => 'nullable|exists:banks,id',
            'cash_account_id' => 'nullable|exists:cash_accounts,id',
            'check_number' => 'nullable|string',
            'check_date' => 'nullable|date',
            'reference_number' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($invoiceId);

        DB::beginTransaction();

        try {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'payment_date' => $validated['payment_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'transaction_type' => $validated['transaction_type'],
                'bank_id' => $validated['bank_id'] ?? null,
                'cash_account_id' => $validated['cash_account_id'] ?? null,
                'check_number' => $validated['check_number'] ?? null,
                'check_date' => $validated['check_date'] ?? null,
            ]);

            // Record payment in IFRS system if it's completed
            if ($payment->status === 'completed' && $payment->transaction_type === 'payment') {
                try {
                    $this->accountingService->recordPayment($payment);
                    Log::info("Payment {$payment->id} successfully recorded in accounting system");
                } catch (Exception $e) {
                    // Log the error but don't fail the payment creation
                    Log::error("Failed to record payment {$payment->id} in accounting system", [
                        'error' => $e->getMessage(),
                        'payment_id' => $payment->id,
                    ]);
                }
            }

            // Update invoice payment tracking fields
            $invoice->refresh();
            $invoice->syncPaymentFields();

            // Update bank/cash account balances
            if ($payment->status === 'completed') {
                if ($payment->bank_id) {
                    $payment->bank->updateBalance();
                } elseif ($payment->cash_account_id) {
                    $payment->cashAccount->updateBalance();
                }
            }

            DB::commit();

            return back()->with('success', 'Payment added successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Payment creation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to create payment: ' . $e->getMessage());
        }
    }

    public function downloadReceipt($id)
    {
        $payment = Payment::with([
            'invoice', 
            'invoice.customer', 
            'invoice.vehicle',
            'bank',
            'cashAccount'
        ])->findOrFail($id);

        $pdf = PDF::loadView('payments.receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'customer' => $payment->invoice->customer,
            'vehicle' => $payment->invoice->vehicle,
        ]);

        return $pdf->download('payment-receipt-' . $payment->id . '.pdf');
    }
}
