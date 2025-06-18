<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\Vehicle;
use Inertia\Inertia;

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

    public function create()
    {
        $lastInvoice = \App\Models\Invoice::orderBy('created_at', 'desc')->first();
        $nextNumber = 1001;
        if ($lastInvoice && preg_match('/INV-(\d+)/', $lastInvoice->invoice_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        $nextInvoiceNumber = 'INV-' . $nextNumber;

        $customers = Customer::select([
            'id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'drivers_license_number',
            'address',
            'city',
            'country'
        ])->get()->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'drivers_license_number' => $customer->drivers_license_number,
                'address' => $customer->address,
                'city' => $customer->city,
                'country' => $customer->country,
            ];
        });

        $vehicles = Vehicle::select(['id', 'name'])->get();

        return Inertia::render('Invoices/Create', [
            'customers' => $customers,
            'vehicles' => $vehicles,
            'nextInvoiceNumber' => $nextInvoiceNumber,
        ]);
    }

    public function index()
    {
        $invoices = Invoice::with('customer')->orderBy('created_at', 'desc')->get();
        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }
}
