<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\Vehicle;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Contract;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoicePdfMail;
use Illuminate\Support\Facades\Storage;
use App\Services\AccountingService;

class InvoiceController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    private function generateInvoiceNumber()
    {
        $lastInvoice = Invoice::latest()->first();
        $number = $lastInvoice ? (int)substr($lastInvoice->invoice_number, 4) + 1 : 100;
        return 'INV-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $invoices = Invoice::with(['customer', 'vehicle'])
            ->latest()
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'status' => $invoice->payment_status,
                    'total_amount' => (float) $invoice->total_amount,
                    'paid_amount' => (float) $invoice->paid_amount,
                    'remaining_amount' => (float) $invoice->remaining_amount,
                    'customer' => [
                        'name' => $invoice->customer->first_name . ' ' . $invoice->customer->last_name,
                    ],
                    'vehicle' => $invoice->vehicle
                        ? [
                            'make' => $invoice->vehicle->make,
                            'model' => $invoice->vehicle->model,
                            'plate_number' => $invoice->vehicle->plate_number,
                        ]
                        : null,
                ];
            });

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices
        ]);
    }

    public function show($id)
    {
        $invoice = Invoice::with([
            'customer', 
            'vehicle', 
            'items', 
            'payments',
            'contract'
        ])->findOrFail($id);

        // Fetch all applied advances (Quick Pay allocations)
        $appliedCredits = \App\Models\PaymentReceiptAllocation::query()
            ->with(['paymentReceipt'])
            ->where('invoice_id', $invoice->id)
            ->where(function ($q) {
                $q->whereIn('row_id', ['prepayment', 'rental_income', 'vat_collection', 'security_deposit'])
                  ->orWhere('row_id', 'like', 'additional_fee_%');
            })
            ->get()
            ->map(function ($allocation) {
                return [
                    'id' => $allocation->id,
                    'description' => $allocation->description,
                    'row_id' => $allocation->row_id,
                    'amount' => (float) $allocation->amount,
                    'memo' => $allocation->memo,
                    'payment_receipt_id' => $allocation->payment_receipt_id,
                    'payment_date' => $allocation->paymentReceipt?->payment_date,
                    'payment_method' => $allocation->paymentReceipt?->payment_method,
                ];
            });

        $appliedCreditsTotal = (float) $appliedCredits->sum('amount');
        
        // Calculate amounts
        $totalAmount = (float) $invoice->total_amount;
        $paidAmount = (float) $invoice->paid_amount;
        $amountDue = max(0, $totalAmount - $paidAmount - $appliedCreditsTotal);

        // Payment breakdown
        $paymentBreakdown = [
            'invoice_total' => $totalAmount,
            'sub_total' => (float) $invoice->sub_total,
            'vat_amount' => (float) ($invoice->vat_amount ?? 0),
            'vat_rate' => (float) ($invoice->vat_rate ?? 0),
            'total_discount' => (float) $invoice->total_discount,
            'direct_payments' => $paidAmount,
            'applied_advances' => $appliedCreditsTotal,
            'total_paid' => $paidAmount + $appliedCreditsTotal,
            'amount_due' => $amountDue,
        ];

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'status' => $invoice->payment_status,
                'contract_number' => $invoice->contract?->contract_number,
                'total_days' => $invoice->total_days,
                'start_datetime' => $invoice->start_datetime,
                'end_datetime' => $invoice->end_datetime,
                'customer' => [
                    'id' => $invoice->customer->id,
                    'first_name' => $invoice->customer->first_name,
                    'last_name' => $invoice->customer->last_name,
                    'name' => $invoice->customer->first_name . ' ' . $invoice->customer->last_name,
                    'email' => $invoice->customer->email,
                    'phone' => $invoice->customer->phone_number,
                    'address' => $invoice->customer->address,
                    'city' => $invoice->customer->city,
                    'country' => $invoice->customer->nationality,
                ],
                'vehicle' => [
                    'id' => $invoice->vehicle->id,
                    'name' => $invoice->vehicle->make . ' ' . $invoice->vehicle->model,
                    'make' => $invoice->vehicle->make,
                    'model' => $invoice->vehicle->model,
                    'plate_number' => $invoice->vehicle->plate_number,
                ],
                'items' => $invoice->items->map(function ($item) {
                    $amount = (float) $item->amount;
                    $vatAmount = (float) ($item->vat_amount ?? 0);
                    $vatRate = (float) ($item->vat_rate ?? 5.0); // Default 5% VAT
                    $subtotal = (float) ($item->amount_excluding_vat ?? $amount);
                    $total = (float) ($item->amount_including_vat ?? ($subtotal + $vatAmount));
                    
                    return [
                        'description' => $item->description,
                        'quantity' => 1, // Default quantity for now
                        'unit_price' => $subtotal,
                        'subtotal' => $subtotal,
                        'vat_amount' => $vatAmount,
                        'vat_rate' => $vatRate,
                        'total' => $total,
                        'amount' => $amount,
                        'discount' => (float) $item->discount,
                    ];
                }),
                'payments' => $invoice->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'payment_date' => $payment->payment_date,
                        'status' => $payment->status,
                        'notes' => $payment->notes,
                        'reference_number' => $payment->reference_number,
                        'transaction_type' => $payment->transaction_type,
                        'created_at' => $payment->created_at,
                    ];
                }),
                'applied_credits' => $appliedCredits,
                'payment_breakdown' => $paymentBreakdown,
            ],
        ]);
    }

    public function create(Request $request)
    {
        return redirect()->route('contracts.index')
            ->with('error', 'Invoices can only be created through contract finalization.');
    }

    public function store(Request $request)
    {
        return back()->withErrors([
            'error' => 'Invoices can only be created through contract finalization.'
        ]);
    }

    public function downloadPdf($id)
    {
        $invoice = Invoice::with(['customer', 'vehicle', 'items'])->findOrFail($id);
        $customer = $invoice->customer;
        $vehicle = $invoice->vehicle;
        // Applied credits for PDF
        $appliedCredits = \App\Models\PaymentReceiptAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->where(function ($q) {
                $q->where('allocation_type', 'advance_payment')
                  ->orWhere('row_id', 'prepayment')
                  ->orWhere('row_id', 'like', 'additional_fee_%');
            })
            ->get();
        $appliedCreditsTotal = (float) $appliedCredits->sum('amount');
        $amountDue = max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount - $appliedCreditsTotal);

        // Format vehicle name
        if ($vehicle) {
            $vehicle->name = "{$vehicle->year} {$vehicle->make} {$vehicle->model} - {$vehicle->plate_number}";
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'customer' => $customer,
            'vehicle' => $vehicle,
            'appliedCredits' => $appliedCredits,
            'appliedCreditsTotal' => $appliedCreditsTotal,
            'amountDue' => $amountDue,
        ]);
        return $pdf->stream('invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function destroy($id)
    {
        $invoice = \App\Models\Invoice::findOrFail($id);
        $invoice->delete();
        return redirect()->route('invoices')->with('success', 'Invoice deleted successfully.');
    }

    /**
     * Send the invoice PDF to the customer via email.
     */
    public function sendToCustomer(Request $request, $id)
    {
        $invoice = Invoice::with(['customer', 'vehicle', 'items'])->findOrFail($id);
        $customer = $invoice->customer;
        $vehicle = $invoice->vehicle;

        // Use email from request if provided, otherwise use customer's email
        $email = $request->input('email', $customer->email);

        // Generate PDF
        $appliedCredits = \App\Models\PaymentReceiptAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->where(function ($q) {
                $q->where('allocation_type', 'advance_payment')
                  ->orWhere('row_id', 'prepayment')
                  ->orWhere('row_id', 'like', 'additional_fee_%');
            })
            ->get();
        $appliedCreditsTotal = (float) $appliedCredits->sum('amount');
        $amountDue = max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount - $appliedCreditsTotal);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'customer' => $customer,
            'vehicle' => $vehicle,
            'appliedCredits' => $appliedCredits,
            'appliedCreditsTotal' => $appliedCreditsTotal,
            'amountDue' => $amountDue,
        ]);
        $pdfContent = $pdf->output();

        // Send email
        Mail::to($email)->send(new InvoicePdfMail($invoice, $pdfContent));

        return response()->json(['message' => 'Invoice sent to customer email!']);
    }

    /**
     * Get a public PDF link for WhatsApp sharing.
     */
    public function getPublicPdfLink($id)
    {
        $invoice = Invoice::findOrFail($id);
        $pdfPath = "invoices/invoice-{$invoice->invoice_number}.pdf";
        if (!Storage::disk('public')->exists($pdfPath)) {
            $invoice->load(['customer', 'vehicle', 'items']);
            $customer = $invoice->customer;
            $vehicle = $invoice->vehicle;
            $appliedCredits = \App\Models\PaymentReceiptAllocation::query()
                ->where('invoice_id', $invoice->id)
                ->where(function ($q) {
                    $q->where('allocation_type', 'advance_payment')
                      ->orWhere('row_id', 'prepayment')
                      ->orWhere('row_id', 'like', 'additional_fee_%');
                })
                ->get();
            $appliedCreditsTotal = (float) $appliedCredits->sum('amount');
            $amountDue = max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount - $appliedCreditsTotal);
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
                'customer' => $customer,
                'vehicle' => $vehicle,
                'appliedCredits' => $appliedCredits,
                'appliedCreditsTotal' => $appliedCreditsTotal,
                'amountDue' => $amountDue,
            ]);
            Storage::disk('public')->put($pdfPath, $pdf->output());
        }
        $url = Storage::disk('public')->url($pdfPath);
        return response()->json(['url' => $url]);
    }
}
