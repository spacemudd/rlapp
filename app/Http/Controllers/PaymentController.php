<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentController extends Controller
{
    public function store(Request $request, $invoiceId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_date' => 'required|date',
            'status' => 'required|in:completed,pending,failed',
            'notes' => 'nullable|string',
            'transaction_type' => 'required|in:payment,deposit,refund',
        ]);

        $invoice = Invoice::findOrFail($invoiceId);

        Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'reference_number' => null,
            'payment_date' => $validated['payment_date'],
            'status' => $validated['status'],
            'notes' => $validated['notes'],
            'transaction_type' => $validated['transaction_type'],
        ]);

        // تحديث حالة الفاتورة تلقائيًا بناءً على المدفوعات
        $invoice->refresh();
        if ($invoice->remaining_amount <= 0) {
            $invoice->status = 'paid';
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = 'partial_paid';
        } else {
            $invoice->status = 'unpaid';
        }
        $invoice->save();

        return back()->with('success', 'Payment added successfully.');
    }

    public function downloadReceipt($id)
    {
        $payment = Payment::with(['invoice', 'invoice.customer', 'invoice.vehicle'])->findOrFail($id);

        $pdf = PDF::loadView('payments.receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'customer' => $payment->invoice->customer,
            'vehicle' => $payment->invoice->vehicle,
        ]);

        return $pdf->download('payment-receipt-' . $payment->id . '.pdf');
    }
}
