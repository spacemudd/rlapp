<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Invoice;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            ->where('team_id', Auth::user()->team_id);

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
    public function create(Request $request): Response
    {
        $props = [
            'contractNumber' => Contract::generateContractNumber(),
        ];

        // Optional prefill from query params (e.g., coming from reservations list)
        $prefill = [];
        if ($request->filled('customer_id')) {
            $prefill['customer_id'] = (string) $request->query('customer_id');
            // Try to load customer for label
            $customer = Customer::find($prefill['customer_id']);
            if ($customer) {
                $prefill['customer_name'] = trim($customer->first_name . ' ' . $customer->last_name) ?: $customer->name ?? '';
            }
        }

        if ($request->filled('vehicle_id')) {
            $prefill['vehicle_id'] = (string) $request->query('vehicle_id');
            // Try to load vehicle for label
            $vehicle = Vehicle::find($prefill['vehicle_id']);
            if ($vehicle) {
                $vehicleTitle = trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}");
                $prefill['vehicle_label'] = $vehicleTitle !== '' ? $vehicleTitle : ($vehicle->plate_number ?? '');
            }
        }

        if ($request->filled('start_date')) {
            $prefill['start_date'] = (string) $request->query('start_date');
        }

        if ($request->filled('end_date')) {
            $prefill['end_date'] = (string) $request->query('end_date');
        }

        if ($request->filled('daily_rate')) {
            $prefill['daily_rate'] = (float) $request->query('daily_rate');
        }

        if (!empty($prefill)) {
            $props['prefill'] = $prefill;
        }

        // If there's a newly created customer in flash data, include it
        if (session()->has('newCustomer')) {
            $props['newCustomer'] = session('newCustomer');
        }

        // Provide active branches for selection
        $props['branches'] = Branch::active()
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'country']);

        return Inertia::render('Contracts/Create', $props);
    }

    /**
     * Search customers for async dropdown
     */
    public function searchCustomers(Request $request)
    {
        $query = $request->get('query', '');

        $customers = Customer::where('team_id', Auth::user()->team_id)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->with('blockedBy') // Load the user who blocked the customer
            ->orderBy('is_blocked') // Show non-blocked customers first
            ->orderBy('first_name')
            ->limit(20)
            ->get()
            ->map(function ($customer) {
                $label = $customer->first_name . ' ' . $customer->last_name . ' - ' . $customer->phone;

                // Add blocked indicator to label
                if ($customer->is_blocked) {
                    $label = '🚫 ' . $label . ' (BLOCKED)';
                }

                return [
                    'id' => $customer->id,
                    'label' => $label,
                    'value' => $customer->id,
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'drivers_license_number' => $customer->drivers_license_number,
                    'address' => $customer->address,
                    'city' => $customer->city,
                    'country' => $customer->country,
                    'status' => $customer->status,
                    // Add blocking information
                    'is_blocked' => $customer->is_blocked,
                    'block_reason' => $customer->block_reason,
                    'blocked_at' => $customer->blocked_at,
                    'blocked_by' => $customer->blockedBy ? [
                        'id' => $customer->blockedBy->id,
                        'name' => $customer->blockedBy->name,
                    ] : null,
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
     * Calculate rental pricing for a vehicle and date range
     */
    public function calculatePricing(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $vehicle = Vehicle::find($validated['vehicle_id']);
        $pricingService = new \App\Services\PricingService();

        $pricing = $pricingService->calculateRentalPricing(
            $vehicle,
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json($pricing);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'daily_rate' => 'required|numeric|min:0',
            'mileage_limit' => 'nullable|integer|min:0',
            'excess_mileage_rate' => 'nullable|numeric|min:0',
            // 'terms_and_conditions' removed from create flow
            'notes' => 'nullable|string',
            // Override validation
            'override_daily_rate' => 'nullable|boolean',
            'override_final_price' => 'nullable|boolean',
            'final_price_override' => 'nullable|numeric|min:0',
            'override_reason' => 'nullable|string|max:500',
            // Vehicle condition validation (optional during creation)
            'current_mileage' => 'nullable|integer|min:0',
            'fuel_level' => 'nullable|in:full,3/4,1/2,1/4,low,empty',
            'condition_photos.*' => 'nullable|image|mimes:jpeg,png,jpg|max:10240', // 10MB max per image
        ]);

        // Check if customer is blocked
        $customer = Customer::findOrFail($validated['customer_id']);

        if ($customer->team_id !== Auth::user()->team_id) {
            abort(403);
        }

        if ($customer->is_blocked) {
            return back()->withErrors([
                'customer_id' => "Cannot create contract for blocked customer. Reason: {$customer->block_reason}"
            ])->withInput();
        }

        // Handle photo uploads
        $photosPaths = [];
        if ($request->hasFile('condition_photos')) {
            foreach ($request->file('condition_photos') as $photo) {
                $path = $photo->store('contract_photos/pickup', 'public');
                $photosPaths[] = $path;
            }
        }

        // Calculate total days
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays = $startDate->diffInDays($endDate); // Exclude end date (day 1 to day 11 = 10 days)

        // Handle pricing overrides
        $dailyRate = $validated['daily_rate'];
        $totalAmount = $dailyRate * $totalDays;
        $originalCalculatedAmount = null;
        $overrideDailyRate = false;
        $overrideFinalPrice = false;

        // If daily rate override is enabled, use the provided rate
        if ($validated['override_daily_rate'] ?? false) {
            $overrideDailyRate = true;
            $totalAmount = $dailyRate * $totalDays;
        }
        // If final price override is enabled, calculate daily rate from final price
        elseif ($validated['override_final_price'] ?? false) {
            $overrideFinalPrice = true;
            $totalAmount = $validated['final_price_override'];
            $dailyRate = $totalAmount / $totalDays;
        }
        // Use calculated pricing from PricingService
        else {
            $pricingService = new \App\Services\PricingService();
            $vehicle = Vehicle::find($validated['vehicle_id']);
            $pricing = $pricingService->calculateRentalPricing($vehicle, $validated['start_date'], $validated['end_date']);

            $originalCalculatedAmount = $pricing['total_amount'];
            $dailyRate = $pricing['daily_rate'];
            $totalAmount = $pricing['total_amount'];
        }

        $contract = Contract::create([
            'contract_number' => Contract::generateContractNumber(),
            'team_id' => Auth::user()->team_id,
            'customer_id' => $validated['customer_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'branch_id' => $validated['branch_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'daily_rate' => $dailyRate,
            'total_days' => $totalDays,
            'total_amount' => $totalAmount,
            'mileage_limit' => $validated['mileage_limit'] ?? null,
            'excess_mileage_rate' => $validated['excess_mileage_rate'] ?? null,
            // 'terms_and_conditions' removed from create flow
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::user()->name,
            'status' => 'active',
            // Override fields
            'override_daily_rate' => $overrideDailyRate,
            'override_final_price' => $overrideFinalPrice,
            'original_calculated_amount' => $originalCalculatedAmount,
            'override_reason' => $validated['override_reason'] ?? null,
            // Vehicle pickup condition
            'pickup_mileage' => $validated['current_mileage'] ?? null,
            'pickup_fuel_level' => $validated['fuel_level'] ?? null,
            'pickup_condition_photos' => $photosPaths,
        ]);

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contract created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Contract $contract): Response
    {
        $contract->load(['customer', 'vehicle.branch', 'branch', 'invoices', 'extensions']);

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
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'daily_rate' => 'required|numeric|min:0',
            'mileage_limit' => 'nullable|integer|min:0',
            'excess_mileage_rate' => 'nullable|numeric|min:0',
            // 'terms_and_conditions' removed from update flow
            'notes' => 'nullable|string',
        ]);

        // Calculate total days and amount
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays = $startDate->diffInDays($endDate); // Exclude end date (day 1 to day 11 = 10 days)
        $totalAmount = $validated['daily_rate'] * $totalDays;

        $contract->update([
            'customer_id' => $validated['customer_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'branch_id' => $validated['branch_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'daily_rate' => $validated['daily_rate'],
            'total_days' => $totalDays,
            'total_amount' => $totalAmount,
            'mileage_limit' => $validated['mileage_limit'],
            'excess_mileage_rate' => $validated['excess_mileage_rate'],
            // 'terms_and_conditions' removed from update flow
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

        // Redirect to finalization page instead of directly completing
        return redirect()->route('contracts.finalize', $contract);
    }

    /**
     * Show the contract finalization form.
     */
    public function showFinalize(Contract $contract)
    {
        if ($contract->status !== 'active') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only active contracts can be finalized.');
        }

        return inertia('Contracts/Finalize', [
            'contract' => $contract->load(['customer', 'vehicle']),
        ]);
    }

    /**
     * Finalize and complete a contract with return conditions.
     */
    public function finalize(Request $request, Contract $contract): RedirectResponse
    {
        if ($contract->status !== 'active') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only active contracts can be finalized.');
        }

        $validated = $request->validate([
            'return_mileage' => [
                'required',
                'integer',
                'min:' . ($contract->pickup_mileage ?? 0),
            ],
            'return_fuel_level' => 'required|in:full,3/4,1/2,1/4,low,empty',
            'finalization_notes' => 'nullable|string|max:2000',
        ]);

        // Record vehicle return using the model method
        $contract->recordVehicleReturn(
            $validated['return_mileage'],
            $validated['return_fuel_level'],
            []
        );

        // Add finalization notes to the contract notes
        if (!empty($validated['finalization_notes'])) {
            $existingNotes = $contract->notes ? $contract->notes . "\n\n" : '';
            $contract->update([
                'notes' => $existingNotes . "Finalization Notes:\n" . $validated['finalization_notes']
            ]);
        }

        // Complete the contract
        $contract->complete();

        $additionalCharges = $contract->getTotalAdditionalCharges();
        $message = 'Contract finalized and completed successfully.';

        if ($additionalCharges > 0) {
            $message .= ' Additional charges of ' . $contract->currency . ' ' . number_format($additionalCharges, 2) . ' have been calculated.';
        }

        return redirect()->route('contracts.show', $contract)
            ->with('success', $message);
    }

    /**
     * Record vehicle return and complete contract.
     */
    public function recordReturn(Request $request, Contract $contract): RedirectResponse
    {
        // Only allow recording return for active contracts
        if ($contract->status !== 'active') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only active contracts can have returns recorded.');
        }

        $validated = $request->validate([
            'return_mileage' => 'required|integer|min:0',
            'return_fuel_level' => 'required|in:full,3/4,1/2,1/4,low,empty',
            'return_condition_photos.*' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        // Handle return photos upload
        $returnPhotosPaths = [];
        if ($request->hasFile('return_condition_photos')) {
            foreach ($request->file('return_condition_photos') as $photo) {
                $path = $photo->store('contract_photos/return', 'public');
                $returnPhotosPaths[] = $path;
            }
        }

        // Record vehicle return using the model method
        $contract->recordVehicleReturn(
            $validated['return_mileage'],
            $validated['return_fuel_level'],
            $returnPhotosPaths
        );

        // Optionally complete the contract automatically
        // You can make this configurable based on business logic
        if ($request->input('complete_contract', false)) {
            $contract->complete();
        }

        $additionalCharges = $contract->getTotalAdditionalCharges();
        $message = 'Vehicle return recorded successfully.';

        if ($additionalCharges > 0) {
            $message .= ' Additional charges of AED ' . number_format($additionalCharges, 2) . ' have been calculated.';
        }

        return redirect()->route('contracts.show', $contract)
            ->with('success', $message);
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

        // Note: Deposit is now handled exclusively in the invoicing flow; no auto-adding here.

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

    /**
     * Extend a contract by a specified number of days.
     */
    public function extend(Request $request, Contract $contract): RedirectResponse
    {
        if ($contract->status !== 'active') {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Only active contracts can be extended.');
        }

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $extension = $contract->extend(
                $validated['days'],
                $validated['reason'] ?? null
            );

            return redirect()->route('contracts.show', $contract)
                ->with('success', "Contract extended by {$validated['days']} days successfully. Extension amount: " . number_format($extension->total_amount, 2) . " AED");
        } catch (\Exception $e) {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'Error extending contract: ' . $e->getMessage());
        }
    }

    /**
     * Calculate pricing for a contract extension.
     */
    public function calculateExtensionPricing(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        try {
            // Load the vehicle relation
            $contract->load('vehicle');

            $pricingService = new \App\Services\PricingService();
            $pricing = $pricingService->calculatePricingForDays($contract->vehicle, $validated['days']);

            return response()->json([
                'success' => true,
                'pricing' => $pricing,
                'daily_rate' => $pricing['effective_daily_rate'],
                'total_amount' => $pricing['total_amount'],
                'pricing_tier' => $pricing['tier'],
                'breakdown' => $pricing['breakdown'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error calculating pricing: ' . $e->getMessage(),
            ], 500);
        }
    }
}
