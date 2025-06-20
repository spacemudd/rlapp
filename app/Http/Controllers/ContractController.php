<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Contract::with(['customer', 'vehicle', 'invoices'])
            ->where('team_id', auth()->user()->team_id);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('contract_number', 'like', "%{$searchTerm}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                        $customerQuery->where('first_name', 'like', "%{$searchTerm}%")
                            ->orWhere('last_name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($searchTerm) {
                        $vehicleQuery->where('plate_number', 'like', "%{$searchTerm}%")
                            ->orWhere('make', 'like', "%{$searchTerm}%")
                            ->orWhere('model', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Sort by latest
        $query->latest();

        $contracts = $query->paginate(10)->withQueryString();

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status ?? 'all',
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $props = [
            'contractNumber' => Contract::generateContractNumber(),
        ];

        // If there's a newly created customer in flash data, include it
        if (session()->has('newCustomer')) {
            $props['newCustomer'] = session('newCustomer');
        }

        return Inertia::render('Contracts/Create', $props);
    }

    /**
     * Search customers for async dropdown
     */
    public function searchCustomers(Request $request)
    {
        $query = $request->get('query', '');

        $customers = Customer::where('team_id', auth()->user()->team_id)
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->orderBy('first_name')
            ->limit(20)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'label' => $customer->first_name . ' ' . $customer->last_name . ' - ' . $customer->phone,
                    'value' => $customer->id,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'phone' => $customer->phone,
                ];
            });

        return response()->json($customers);
    }

    /**
     * Search vehicles for async dropdown
     */
    public function searchVehicles(Request $request)
    {
        $query = $request->get('query', '');

        $vehicles = Vehicle::where('status', 'available')
            ->where(function ($q) use ($query) {
                $q->where('make', 'like', "%{$query}%")
                    ->orWhere('model', 'like', "%{$query}%")
                    ->orWhere('plate_number', 'like', "%{$query}%")
                    ->orWhere('chassis_number', 'like', "%{$query}%");
            })
            ->orderBy('make')
            ->orderBy('model')
            ->limit(20)
            ->get()
            ->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'label' => $vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model . ' - ' . $vehicle->plate_number,
                    'value' => $vehicle->id,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'plate_number' => $vehicle->plate_number,
                    'price_daily' => $vehicle->price_daily,
                    'price_weekly' => $vehicle->price_weekly,
                    'price_monthly' => $vehicle->price_monthly,
                ];
            });

        return response()->json($vehicles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'daily_rate' => 'required|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'mileage_limit' => 'nullable|integer|min:0',
            'excess_mileage_rate' => 'nullable|numeric|min:0',
            'terms_and_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Calculate total days and amount
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalAmount = $validated['daily_rate'] * $totalDays;

        $contract = Contract::create([
            'contract_number' => Contract::generateContractNumber(),
            'team_id' => auth()->user()->team_id,
            'customer_id' => $validated['customer_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'daily_rate' => $validated['daily_rate'],
            'total_days' => $totalDays,
            'total_amount' => $totalAmount,
            'deposit_amount' => $validated['deposit_amount'] ?? 0,
            'mileage_limit' => $validated['mileage_limit'],
            'excess_mileage_rate' => $validated['excess_mileage_rate'],
            'terms_and_conditions' => $validated['terms_and_conditions'],
            'notes' => $validated['notes'],
            'created_by' => auth()->user()->name,
            'status' => 'draft',
        ]);

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contract created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Contract $contract): Response
    {
        $contract->load(['customer', 'vehicle', 'invoices']);

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contract $contract): Response
    {
        // Only allow editing of draft contracts
        if ($contract->status !== 'draft') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only draft contracts can be edited.');
        }

        // Load the customer and vehicle relationships
        $contract->load(['customer', 'vehicle']);

        return Inertia::render('Contracts/Edit', [
            'contract' => $contract,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contract $contract): RedirectResponse
    {
        // Only allow updating of draft contracts
        if ($contract->status !== 'draft') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only draft contracts can be updated.');
        }

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'daily_rate' => 'required|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'mileage_limit' => 'nullable|integer|min:0',
            'excess_mileage_rate' => 'nullable|numeric|min:0',
            'terms_and_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Calculate total days and amount
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalAmount = $validated['daily_rate'] * $totalDays;

        $contract->update([
            'customer_id' => $validated['customer_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'daily_rate' => $validated['daily_rate'],
            'total_days' => $totalDays,
            'total_amount' => $totalAmount,
            'deposit_amount' => $validated['deposit_amount'] ?? 0,
            'mileage_limit' => $validated['mileage_limit'],
            'excess_mileage_rate' => $validated['excess_mileage_rate'],
            'terms_and_conditions' => $validated['terms_and_conditions'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contract updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contract $contract): RedirectResponse
    {
        // Only allow deletion of draft contracts
        if ($contract->status !== 'draft') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only draft contracts can be deleted.');
        }

        $contract->delete();

        return redirect()->route('contracts.index')
            ->with('success', 'Contract deleted successfully.');
    }

    /**
     * Activate a contract.
     */
    public function activate(Contract $contract): RedirectResponse
    {
        if ($contract->status !== 'draft') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only draft contracts can be activated.');
        }

        $contract->activate();

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contract activated successfully.');
    }

    /**
     * Complete a contract.
     */
    public function complete(Contract $contract): RedirectResponse
    {
        if ($contract->status !== 'active') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only active contracts can be completed.');
        }

        $contract->complete();

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contract completed successfully.');
    }

    /**
     * Void a contract.
     */
    public function void(Request $request, Contract $contract): RedirectResponse
    {
        if (in_array($contract->status, ['completed', 'void'])) {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Cannot void a completed or already voided contract.');
        }

        $validated = $request->validate([
            'void_reason' => 'required|string|max:1000',
        ]);

        $contract->void($validated['void_reason']);

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contract voided successfully.');
    }

    /**
     * Create an invoice for the contract.
     */
    public function createInvoice(Contract $contract): RedirectResponse
    {
        if ($contract->status === 'void') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Cannot create invoice for voided contract.');
        }

        // Create invoice with contract details
        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'customer_id' => $contract->customer_id,
            'vehicle_id' => $contract->vehicle_id,
            'contract_id' => $contract->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'currency' => $contract->currency ?? 'AED',
            'total_days' => $contract->total_days,
            'start_datetime' => $contract->start_date,
            'end_datetime' => $contract->end_date,
            'sub_total' => $contract->total_amount,
            'total_discount' => 0,
            'total_amount' => $contract->total_amount,
            // 'team_id' => $contract->team_id,
        ]);

        // Create invoice items
        $invoice->items()->create([
            'description' => "Car Rental - {$contract->total_days} days @ " . number_format($contract->daily_rate, 2) . " AED/day",
            'amount' => $contract->total_amount,
            'discount' => 0,
        ]);

        // Add deposit as separate item if exists
        if ($contract->deposit_amount > 0) {
            $invoice->items()->create([
                'description' => 'Security Deposit',
                'amount' => $contract->deposit_amount,
                'discount' => 0,
            ]);

            // Update invoice totals
            $invoice->update([
                'sub_total' => $contract->total_amount + $contract->deposit_amount,
                'total_amount' => $contract->total_amount + $contract->deposit_amount,
            ]);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created successfully for the contract.');
    }

    /**
     * Download contract as PDF.
     */
    public function downloadPdf(Contract $contract)
    {
        $contract->load(['customer', 'vehicle']);

        // Generate PDF using Snappy (wkhtmltopdf)
        $pdf = PDF::loadView('contracts.pdf', [
            'contract' => $contract,
            'customer' => $contract->customer,
            'vehicle' => $contract->vehicle,
        ]);

        // Set options for better Arabic support
        $pdf->setOptions([
            'page-size' => 'A4',
            'margin-top' => '0.75in',
            'margin-right' => '0.75in',
            'margin-bottom' => '0.75in',
            'margin-left' => '0.75in',
            'encoding' => 'UTF-8',
            'enable-local-file-access' => true,
            'no-outline' => true,
            'disable-smart-shrinking' => true,
        ]);

        return $pdf->stream('contract-' . $contract->contract_number . '.pdf');
    }
}
