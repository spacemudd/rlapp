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

class InvoiceController extends Controller
{
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
                    'status' => $invoice->status,
                    'currency' => $invoice->currency,
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
        $invoice = Invoice::with(['customer', 'vehicle', 'items', 'payments'])->findOrFail($id);

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'total_days' => $invoice->total_days,
                'start_datetime' => $invoice->start_datetime,
                'end_datetime' => $invoice->end_datetime,
                'sub_total' => (float) $invoice->sub_total,
                'total_discount' => (float) $invoice->total_discount,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'remaining_amount' => (float) $invoice->remaining_amount,
                'customer' => [
                    'id' => $invoice->customer->id,
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
                    return [
                        'description' => $item->description,
                        'amount' => (float) $item->amount,
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
            ],
        ]);
    }

    public function create(Request $request)
    {
        $lastInvoice = Invoice::orderBy('created_at', 'desc')->first();
        $nextNumber = 10001; // Start from 10001

        if ($lastInvoice && preg_match('/INV-(\d+)/', $lastInvoice->invoice_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }

        $nextInvoiceNumber = 'INV-' . $nextNumber;

        $contracts = \App\Models\Contract::with('vehicle')->get()->map(function($contract) {
            return [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'vehicle_id' => $contract->vehicle_id,
                'start_date' => $contract->start_date,
                'end_date' => $contract->end_date,
                'total_days' => $contract->total_days,
                'customer_id' => $contract->customer_id,
            ];
        });

        return Inertia::render('Invoices/Create', [
            'contracts' => $contracts,
            'nextInvoiceNumber' => $nextInvoiceNumber,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'invoice_date' => 'required|date',
                'due_date' => 'required|date',
                'status' => 'required|in:paid,fully_paid,partial_paid,unpaid',
                'currency' => 'required|string',
                'total_days' => 'required|integer|min:1',
                'start_datetime' => 'required|date',
                'end_datetime' => 'required|date|after:start_datetime',
                'vehicle_id' => 'required|exists:vehicles,id',
                'customer_id' => 'required|exists:customers,id',
                'sub_total' => 'required|numeric|min:0',
                'total_discount' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'items' => 'required|array|min:1',
                'items.*.description' => 'nullable|string',
                'items.*.amount' => 'nullable|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0',
            ]);

            // Generate invoice number
            $lastInvoice = Invoice::orderBy('created_at', 'desc')->first();
            $nextNumber = 10001;
            if ($lastInvoice && preg_match('/INV-(\d+)/', $lastInvoice->invoice_number, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }
            $invoiceNumber = 'INV-' . $nextNumber;

            DB::beginTransaction();

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'],
                'currency' => $validated['currency'],
                'total_days' => $validated['total_days'],
                'start_datetime' => $validated['start_datetime'],
                'end_datetime' => $validated['end_datetime'],
                'vehicle_id' => $validated['vehicle_id'],
                'customer_id' => $validated['customer_id'],
                'sub_total' => $validated['sub_total'],
                'total_discount' => $validated['total_discount'],
                'total_amount' => $validated['total_amount'],
            ]);

            // Create invoice items
            foreach ($request->items as $item) {
                if (!empty($item['description'])) {
                    $invoice->items()->create([
                        'description' => $item['description'],
                        'amount' => $item['amount'] ?? 0,
                        'discount' => $item['discount'] ?? 0,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('invoices')
                ->with('success', 'Invoice created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invoice creation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to create invoice: ' . $e->getMessage())->withInput();
        }
    }

    public function downloadPdf($id)
    {
        $invoice = Invoice::with(['customer', 'vehicle', 'items'])->findOrFail($id);
        $customer = $invoice->customer;
        $vehicle = $invoice->vehicle;

        // Format vehicle name
        if ($vehicle) {
            $vehicle->name = "{$vehicle->year} {$vehicle->make} {$vehicle->model} - {$vehicle->plate_number}";
        }

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'customer', 'vehicle'));
        return $pdf->stream('invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function destroy($id)
    {
        $invoice = \App\Models\Invoice::findOrFail($id);
        $invoice->delete();
        return redirect()->route('invoices')->with('success', 'Invoice deleted successfully.');
    }
}
