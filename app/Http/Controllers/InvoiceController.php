<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

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
                        'first_name' => $invoice->customer->first_name,
                        'last_name' => $invoice->customer->last_name,
                    ],
                    'vehicle' => [
                        'make' => $invoice->vehicle->make,
                        'model' => $invoice->vehicle->model,
                        'plate_number' => $invoice->vehicle->plate_number,
                    ],
                ];
            });

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices
        ]);
    }

    public function create()
    {
        return Inertia::render('Invoices/Create', [
            'invoice_number' => $this->generateInvoiceNumber(),
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
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:invoices',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => 'required|in:paid,unpaid,partial',
            'currency' => 'required|string|size:3',
            'total_days' => 'required|integer|min:1',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date',
            'vehicle_id' => 'required|uuid|exists:vehicles,id',
            'customer_id' => 'required|uuid|exists:customers,id',
            'sub_total' => 'required|numeric|min:0',
            'total_discount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
        ]);

        // Create the invoice
        $invoice = Invoice::create($validated);

        // Update the vehicle status to 'rented' if the invoice is created
        $vehicle = \App\Models\Vehicle::find($validated['vehicle_id']);
        if ($vehicle) {
            $vehicle->update(['status' => 'rented']);
        }

        return redirect()->route('invoices')->with('success', 'Invoice created successfully.');
    }

    public function show(Invoice $invoice)
    {
        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice->load(['vehicle', 'customer'])
        ]);
    }

    public function edit(Invoice $invoice)
    {
        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice->load(['vehicle', 'customer'])
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:invoices,invoice_number,' . $invoice->id,
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => 'required|in:paid,unpaid,partial',
            'currency' => 'required|string|size:3',
            'total_days' => 'required|integer|min:1',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date',
            'vehicle_id' => 'required|uuid|exists:vehicles,id',
            'customer_id' => 'required|uuid|exists:customers,id',
            'sub_total' => 'required|numeric|min:0',
            'total_discount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
        ]);

        $invoice->update($validated);

        return redirect()->route('invoices')->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return redirect()->route('invoices')->with('success', 'Invoice deleted successfully.');
    }

    public function generatePdf(Invoice $invoice)
    {
        $invoice->load(['customer', 'vehicle']);

        $data = [
            'invoice' => $invoice,
            'luxuriaLogo' => base64_encode(file_get_contents(public_path('img/rentluxurialogo.png'))),
            // You might add transaction data here if you have a transactions table related to invoices
            'transactions' => [
                ['number' => '13538-4753', 'date' => '2025-05-25 15:57', 'method' => 'Credit Card', 'category' => 'Security Deposit', 'amount' => -1510.00],
                ['number' => '13539-4754', 'date' => '2025-05-25 15:57', 'method' => 'Credit Card', 'category' => 'damage income', 'amount' => 1510.00],
                ['number' => '13475-4732', 'date' => '2025-05-24 17:19', 'method' => 'Bank Transfer', 'category' => 'Security Deposit', 'amount' => -20.00],
                ['number' => '13476-4733', 'date' => '2025-05-24 17:19', 'method' => 'Bank Transfer', 'category' => 'Salik', 'amount' => 20.00],
                ['number' => '13470-4729', 'date' => '2025-05-24 17:09', 'method' => 'Bank Transfer', 'category' => 'Security Deposit', 'amount' => -145.00],
            ],
            'invoiceItems' => [
                ['description' => 'Delivery', 'amount' => 300.00, 'discount' => 0.00, 'total' => 300.00, 'balance' => 300.00],
                ['description' => 'DEPOSIT ALLOWANCE', 'amount' => 120.00, 'discount' => 0.00, 'total' => 120.00, 'balance' => 120.00],
                ['description' => 'Fuel Charges', 'amount' => 125.00, 'discount' => 0.00, 'total' => 125.00, 'balance' => 125.00],
                ['description' => 'Traffic Fines', 'amount' => 495.00, 'discount' => 0.00, 'total' => 495.00, 'balance' => 495.00],
                ['description' => 'Salik Fees', 'amount' => 20.00, 'discount' => 0.00, 'total' => 20.00, 'balance' => 20.00],
                ['description' => 'Damages', 'amount' => 1510.00, 'discount' => 0.00, 'total' => 1510.00, 'balance' => 1510.00],
                ['description' => 'Rental Fees', 'amount' => 4800.00, 'discount' => 0.00, 'total' => 4800.00, 'balance' => 4800.00],
                ['description' => '- Rent Vehicle Land Rover Range Rover Vogue, Plate Number: F-98303, Daily Rate: 1200.00', 'amount' => 4800.00, 'discount' => 0.00, 'total' => 4800.00, 'balance' => 4800.00],
            ]
        ];

        $pdf = PDF::loadView('invoices.pdf', $data);
        return $pdf->stream('invoice-' . $invoice->invoice_number . '.pdf');
    }
}
