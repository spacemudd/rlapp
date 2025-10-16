<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Invoice;
use App\Models\Branch;
use App\Models\PaymentReceipt;
use App\Services\AccountingService;
use App\Services\ContractClosureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Support\Facades\App;

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

        $vehicles = Vehicle::with([
                'contracts' => function ($q) {
                    $q->whereIn('status', ['draft', 'active'])->latest()->limit(1);
                },
                'vehicleMake',
                'vehicleModel'
            ])
            ->where('status', 'available')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('make', 'like', "%{$query}%")
                    ->orWhere('model', 'like', "%{$query}%")
                    ->orWhere('plate_number', 'like', "%{$query}%")
                    ->orWhere('chassis_number', 'like', "%{$query}%")
                    ->orWhereHas('vehicleMake', function ($makeQuery) use ($query) {
                        $makeQuery->where('name_en', 'like', "%{$query}%")
                                 ->orWhere('name_ar', 'like', "%{$query}%");
                    })
                    ->orWhereHas('vehicleModel', function ($modelQuery) use ($query) {
                        $modelQuery->where('name_en', 'like', "%{$query}%")
                                  ->orWhere('name_ar', 'like', "%{$query}%");
                    });
            })
            ->orderBy('make')
            ->orderBy('model')
            ->limit(20)
            ->get()
            ->map(function ($vehicle) {
                $hasActiveContract = $vehicle->contracts->isNotEmpty();
                $contractStatus = $hasActiveContract ? $vehicle->contracts->first()->status : null;
                
                return [
                    'id' => $vehicle->id,
                    'label' => $vehicle->full_name_localized . ' - ' . $vehicle->plate_number,
                    'value' => $vehicle->id,
                    'make' => $vehicle->make_name,
                    'model' => $vehicle->model_name,
                    'year' => $vehicle->year,
                    'plate_number' => $vehicle->plate_number,
                    'price_daily' => $vehicle->price_daily,
                    'price_weekly' => $vehicle->price_weekly,
                    'price_monthly' => $vehicle->price_monthly,
                    'disabled' => $hasActiveContract,
                    'contract_status' => $contractStatus,
                    'unavailable_reason' => $hasActiveContract ? 
                        ($contractStatus === 'active' ? __('words.already_has_active_contract') : __('words.has_draft_contract')) : 
                        null,
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
        $contract->load(['customer', 'vehicle.branch', 'branch', 'invoices', 'extensions', 'paymentReceipts.allocations.glAccount']);

        $breadcrumbs = [
            [
                'title' => __('words.dashboard'),
                'href' => route('dashboard'),
            ],
            [
                'title' => __('words.contracts'),
                'href' => route('contracts.index'),
            ],
            [
                'title' => $contract->contract_number,
            ],
        ];

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
            'breadcrumbs' => $breadcrumbs,
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

        // Redirect to invoice creation page prefilled with contract; do not persist yet
        return redirect()->route('invoices.create', ['contract_id' => $contract->id]);
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

    /**
     * Quick Pay: return a grouped summary of open balances for allocation.
     */
    public function quickPaySummary(Request $request, Contract $contract)
    {
        $contract->load(['vehicle.branch', 'customer']);

        // Get branch quick pay account mappings
        $branchQp = $contract->vehicle?->branch?->quick_pay_accounts ?? [];
        $liabilityMap = $branchQp['liability'] ?? [];
        $incomeMap = $branchQp['income'] ?? [];

        // Get GL account details for the mapped accounts
        $glAccountIds = array_merge(array_values($liabilityMap), array_values($incomeMap));
        $glAccounts = \IFRS\Models\Account::whereIn('id', $glAccountIds)->get()->keyBy('id');

        $response = [
            'contract_id' => (string) $contract->id,
            'currency' => $contract->currency ?? 'AED',
            'sections' => [
                [
                    'key' => 'liability',
                    'rows' => [
                        [
                            'id' => 'violation_guarantee',
                            'description' => __('words.qp_violation_guarantee'),
                            'gl_account_id' => $liabilityMap['violation_guarantee'] ?? '',
                            'gl_account' => $glAccounts->get($liabilityMap['violation_guarantee'] ?? '')?->name ?? __('words.qp_violation_guarantee'),
                            'total' => 0,
                            'paid' => 0,
                            'remaining' => 0,
                            'amount' => 0,
                            'editable' => true,
                        ],
                        [
                            'id' => 'prepayment',
                            'description' => __('words.qp_prepayment'),
                            'gl_account_id' => $liabilityMap['prepayment'] ?? '',
                            'gl_account' => $glAccounts->get($liabilityMap['prepayment'] ?? '')?->name ?? __('words.qp_prepayment'),
                            'total' => 0,
                            'paid' => 0,
                            'remaining' => 0,
                            'amount' => 0,
                            'editable' => true,
                        ],
                        [
                            'id' => 'rental_income',
                            'description' => __('words.qp_rental_income'),
                            'gl_account_id' => $liabilityMap['rental_income'] ?? '',
                            'gl_account' => $glAccounts->get($liabilityMap['rental_income'] ?? '')?->name ?? __('words.qp_rental_income'),
                            'total' => 0,
                            'paid' => 0,
                            'remaining' => 0,
                            'amount' => 0,
                            'editable' => true,
                        ],
                        [
                            'id' => 'vat_collection',
                            'description' => __('words.qp_vat_collection'),
                            'gl_account_id' => $liabilityMap['vat_collection'] ?? '',
                            'gl_account' => $glAccounts->get($liabilityMap['vat_collection'] ?? '')?->name ?? __('words.qp_vat_collection'),
                            'total' => 0,
                            'paid' => 0,
                            'remaining' => 0,
                            'amount' => 0,
                            'editable' => true,
                        ],
                        [
                            'id' => 'salik_fees',
                            'description' => __('words.qp_salik_fees'),
                            'gl_account_id' => $liabilityMap['salik_fees'] ?? '',
                            'gl_account' => $glAccounts->get($liabilityMap['salik_fees'] ?? '')?->name ?? __('words.qp_salik_fees'),
                            'total' => 0,
                            'paid' => 0,
                            'remaining' => 0,
                            'amount' => 0,
                            'editable' => true,
                        ],
                    ],
                ],
                [
                    'key' => 'income',
                    'rows' => [
                        [
                            'id' => 'insurance_fee',
                            'description' => __('words.qp_insurance_fee'),
                            'gl_account_id' => $incomeMap['insurance_fee'] ?? '',
                            'gl_account' => $glAccounts->get($incomeMap['insurance_fee'] ?? '')?->name ?? __('words.qp_insurance_fee'),
                            'total' => 0,
                            'paid' => 0,
                            'remaining' => 0,
                            'amount' => 0,
                            'editable' => true,
                        ],
                        [
                            'id' => 'fines',
                            'description' => __('words.qp_fines'),
                            'gl_account_id' => $incomeMap['fines'] ?? '',
                            'gl_account' => $glAccounts->get($incomeMap['fines'] ?? '')?->name ?? __('words.qp_fines'),
                            'total' => 0,
                            'paid' => 0,
                            'remaining' => 0,
                            'amount' => 0,
                            'editable' => true,
                        ],
                    ],
                ],
            ],
            'totals' => [
                'payable_now' => 0,
                'allocated' => 0,
                'remaining_to_allocate' => 0,
            ],
        ];

        // Add aggregated additional fees to liability section (split into subtotal and VAT)
        try {
            $additionalFees = \App\Models\ContractAdditionalFee::query()
                ->where('contract_id', $contract->id)
                ->selectRaw('fee_type, SUM(subtotal) as total_subtotal, SUM(vat_amount) as total_vat, SUM(total) as total_amount')
                ->groupBy('fee_type')
                ->get();

            $feeTypes = \App\Models\SystemSetting::getFeeTypes();
            $feeTypeMap = collect($feeTypes)->keyBy('key');
            
            // Get VAT Collection account from branch configuration
            $vatCollectionAccountId = $liabilityMap['vat_collection'] ?? '';

            foreach ($additionalFees as $aggregatedFee) {
                $feeTypeKey = $aggregatedFee->fee_type;
                $feeTypeInfo = $feeTypeMap->get($feeTypeKey);
                
                // Get localized name based on app locale
                $locale = app()->getLocale();
                $feeTypeName = $feeTypeInfo[$locale] ?? $feeTypeInfo['en'] ?? $feeTypeKey;
                
                $additionalFeesAccountId = $liabilityMap['additional_fees'] ?? '';
                
                // Create subtotal row (deferred revenue/liability line item)
                if ($aggregatedFee->total_subtotal > 0) {
                    $subtotalRowId = "additional_fee_{$feeTypeKey}";
                    
                    // Calculate paid amount for subtotal row
                    $subtotalPaid = (float) \App\Models\PaymentReceiptAllocation::query()
                        ->where('row_id', $subtotalRowId)
                        ->whereHas('paymentReceipt', function ($q) use ($contract) {
                            $q->where('contract_id', $contract->id)
                              ->where('status', 'completed');
                        })
                        ->sum('amount');
                    
                    $subtotalRemaining = max(0, $aggregatedFee->total_subtotal - $subtotalPaid);
                    
                    $response['sections'][0]['rows'][] = [
                        'id' => $subtotalRowId,
                        'description' => $feeTypeName,
                        'gl_account_id' => $additionalFeesAccountId,
                        'gl_account' => $glAccounts->get($additionalFeesAccountId)?->name ?? $feeTypeName,
                        'total' => round((float) $aggregatedFee->total_subtotal, 2),
                        'paid' => round($subtotalPaid, 2),
                        'remaining' => round($subtotalRemaining, 2),
                        'amount' => 0,
                        'editable' => true,
                    ];
                }
                
                // Create VAT row (VAT Collection liability line item)
                if ($aggregatedFee->total_vat > 0) {
                    $vatRowId = "additional_fee_{$feeTypeKey}_vat";
                    $vatDescription = __('words.vat_for_fee', ['fee' => $feeTypeName]);
                    
                    // Calculate paid amount for VAT row
                    $vatPaid = (float) \App\Models\PaymentReceiptAllocation::query()
                        ->where('row_id', $vatRowId)
                        ->whereHas('paymentReceipt', function ($q) use ($contract) {
                            $q->where('contract_id', $contract->id)
                              ->where('status', 'completed');
                        })
                        ->sum('amount');
                    
                    $vatRemaining = max(0, $aggregatedFee->total_vat - $vatPaid);
                    
                    $response['sections'][0]['rows'][] = [
                        'id' => $vatRowId,
                        'description' => $vatDescription,
                        'gl_account_id' => $vatCollectionAccountId,
                        'gl_account' => $glAccounts->get($vatCollectionAccountId)?->name ?? __('words.qp_vat_collection'),
                        'total' => round((float) $aggregatedFee->total_vat, 2),
                        'paid' => round($vatPaid, 2),
                        'remaining' => round($vatRemaining, 2),
                        'amount' => 0,
                        'editable' => true,
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to add additional fees to quick pay summary', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Calculate consumed rental income to date (accrued)
        try {
            $tz = config('app.timezone', 'UTC');
            $start = \Carbon\Carbon::parse($contract->start_date)->timezone($tz)->startOfDay();
            $end = \Carbon\Carbon::parse($contract->end_date)->timezone($tz)->startOfDay();
            $now = now()->timezone($tz)->startOfDay();

            // Clamp now to contract period
            $clampedNow = $now->lt($start) ? $start : ($now->gt($end) ? $end : $now);
            $daysConsumed = 0;
            if ($clampedNow->gte($start)) {
                // +1 to include the start day itself
                $daysConsumed = $start->diffInDays($clampedNow) + 1;
            }
            $totalDays = max(0, (int) $contract->total_days);
            $daysConsumed = min($daysConsumed, $totalDays);

            // Prefer per-day derived from total to avoid overridden VAT/rounding mismatches
            $perDay = 0.0;
            if ($totalDays > 0 && $contract->total_amount !== null) {
                $perDay = round(((float) $contract->total_amount) / $totalDays, 2);
            } else {
                $perDay = round((float) ($contract->daily_rate ?? 0), 2);
            }
            $consumedAmount = round($perDay * $daysConsumed, 2);

            // Sum of amounts allocated to rental_income via Quick Pay receipts
            $paidToRental = (float) \App\Models\PaymentReceiptAllocation::query()
                ->where('row_id', 'rental_income')
                ->whereHas('paymentReceipt', function ($q) use ($contract) {
                    $q->where('contract_id', $contract->id)
                      ->where('status', 'completed');
                })
                ->sum('amount');

            $remaining = max(0, $consumedAmount - $paidToRental);

            // Inject values into the response for the rental_income row
            foreach ($response['sections'] as &$section) {
                if (($section['key'] ?? null) === 'income') {
                    foreach ($section['rows'] as &$row) {
                        if (($row['id'] ?? null) === 'rental_income') {
                            $row['total'] = $consumedAmount;
                            $row['paid'] = round($paidToRental, 2);
                            $row['remaining'] = round($remaining, 2);
                            break;
                        }
                    }
                }
            }
            unset($section, $row);
        } catch (\Throwable $e) {
            \Log::warning('Failed to compute consumed rental income for quick pay summary', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Calculate paid amounts for ALL rows from PaymentReceiptAllocation
        try {
            $paidAmounts = \App\Models\PaymentReceiptAllocation::query()
                ->whereHas('paymentReceipt', function ($q) use ($contract) {
                    $q->where('contract_id', $contract->id)
                      ->where('status', 'completed');
                })
                ->selectRaw('row_id, SUM(amount) as total_paid')
                ->groupBy('row_id')
                ->pluck('total_paid', 'row_id')
                ->toArray();

            // Update all rows with their paid amounts
            foreach ($response['sections'] as &$section) {
                foreach ($section['rows'] as &$row) {
                    $rowId = $row['id'] ?? '';
                    $paidAmount = (float) ($paidAmounts[$rowId] ?? 0);
                    
                    // For rental_income, we already calculated this above, so skip
                    if ($rowId === 'rental_income') {
                        continue;
                    }
                    
                    // Update paid amount
                    $row['paid'] = round($paidAmount, 2);
                    
                    // Calculate remaining (total - paid)
                    $total = (float) ($row['total'] ?? 0);
                    $remaining = max(0, $total - $paidAmount);
                    $row['remaining'] = round($remaining, 2);
                }
            }
            unset($section, $row);
        } catch (\Throwable $e) {
            \Log::warning('Failed to compute paid amounts for quick pay summary', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json($response);
    }

    /**
     * Quick Pay: accept allocations and record a receipt.
     */
    public function quickPay(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'reference' => 'nullable|string|max:255',
            'allocations' => 'required|array|min:1',
            'allocations.*.row_id' => 'required|string',
            'allocations.*.amount' => 'required|numeric|min:0',
            'allocations.*.memo' => 'nullable|string',
            'allocations.*.type' => 'nullable|in:security_deposit,advance_payment,invoice_settlement',
            'allocations.*.invoice_id' => 'nullable|uuid|exists:invoices,id',
            'amount_total' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Load contract relationships
            $contract->load(['customer', 'vehicle.branch', 'branch']);

            // Create payment receipt
            $paymentReceipt = PaymentReceipt::create([
                'receipt_number' => PaymentReceipt::generateReceiptNumber(),
                'contract_id' => $contract->id,
                'customer_id' => $contract->customer_id,
                'branch_id' => $contract->branch_id ?? $contract->vehicle->branch_id,
                'total_amount' => $validated['amount_total'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference'] ?? null,
                'payment_date' => now()->toDateString(),
                'status' => 'completed',
                'created_by' => auth()->user()->name ?? 'System',
            ]);

            // Get branch quick pay account mappings
            $branch = $contract->branch ?? $contract->vehicle->branch;
            $branchQp = $branch?->quick_pay_accounts ?? [];
            $liabilityMap = $branchQp['liability'] ?? [];
            $incomeMap = $branchQp['income'] ?? [];
            $allMappings = array_merge($liabilityMap, $incomeMap);

            // Create allocations and IFRS transaction
            $accountingService = app(AccountingService::class);
            $ifrsTransaction = $accountingService->recordPaymentReceipt($paymentReceipt, $validated['allocations'], $allMappings);

            // Update payment receipt with IFRS transaction ID
            $paymentReceipt->update(['ifrs_transaction_id' => $ifrsTransaction->id]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('words.payment_receipt_created_successfully'),
                'receipt' => [
                    'id' => $paymentReceipt->id,
                    'receipt_number' => $paymentReceipt->receipt_number,
                    'total_amount' => $paymentReceipt->total_amount,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quick pay failed', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('words.payment_receipt_creation_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process a refund for a contract
     */
    public function refund(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'reference_number' => 'nullable|string|max:255',
            'reason' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            // Load contract relationships
            $contract->load(['customer', 'vehicle.branch', 'branch']);

            // Get branch for account mappings
            $branch = $contract->branch ?? $contract->vehicle->branch;
            if (!$branch) {
                throw new \Exception('No branch found for contract');
            }

            // Get the accounting service
            $accountingService = app(\App\Services\AccountingService::class);

            // Process the refund
            $transaction = $accountingService->processRefund(
                $contract,
                $validated['amount'],
                $validated['payment_method'],
                $validated['reference_number'] ?? null,
                $validated['reason']
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('words.refund_processed_successfully'),
                'transaction_id' => $transaction->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Refund processing failed', [
                'contract_id' => $contract->id,
                'amount' => $validated['amount'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Close a contract by recording vehicle return and updating status.
     */
    public function close(Request $request, Contract $contract)
    {
        // Validate request
        $validated = $request->validate([
            'return_mileage' => 'required|integer|min:0',
            'return_fuel_level' => 'required|string|in:full,3/4,1/2,1/4,low,empty',
            'return_condition_photos' => 'nullable|array',
            'fuel_charge' => 'nullable|numeric|min:0',
        ]);
        
        // Ensure contract is active
        if ($contract->status !== 'active') {
            return back()->withErrors(['error' => 'Only active contracts can be closed.']);
        }
        
        // Record vehicle return using existing model method
        $contract->recordVehicleReturn(
            returnMileage: $validated['return_mileage'],
            returnFuelLevel: $validated['return_fuel_level'],
            returnPhotos: $validated['return_condition_photos'] ?? []
        );
        
        // Update contract with manual fuel charge if provided
        $contract->update([
            'status' => 'completed',
            'completed_at' => now(),
            'fuel_charge' => $validated['fuel_charge'] ?? 0,
        ]);
        
        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contract closed successfully');
    }

    /**
     * Prepare contract closure with financial summary.
     */
    public function prepareClosure(Contract $contract): Response
    {
        // Ensure contract is active or completed
        if (!in_array($contract->status, ['active', 'completed'])) {
            return redirect()->route('contracts.show', $contract)
                ->withErrors(['error' => 'Only active or completed contracts can be closed.']);
        }

        $closureService = new ContractClosureService();
        $summary = $closureService->getContractSummary($contract);

        return Inertia::render('Contracts/ClosureReview', [
            'contract' => $contract->load(['customer', 'vehicle', 'paymentReceipts.allocations', 'extensions', 'additionalFees']),
            'summary' => $summary,
        ]);
    }

    /**
     * Review and adjust contract closure details.
     */
    public function reviewClosure(Contract $contract, Request $request): Response
    {
        // This will be implemented for manual adjustments
        $closureService = new ContractClosureService();
        $summary = $closureService->getContractSummary($contract);

        return Inertia::render('Contracts/ClosureReview', [
            'contract' => $contract->load(['customer', 'vehicle', 'paymentReceipts.allocations', 'extensions']),
            'summary' => $summary,
            'editing' => true,
        ]);
    }

    /**
     * Finalize contract closure and create invoice with IFRS entries.
     */
    public function finalizeClosure(Contract $contract, Request $request): RedirectResponse
    {
        // Validate request
        $validated = $request->validate([
            'invoice_items' => 'required|array',
            'invoice_items.*.description' => 'required|string',
            'invoice_items.*.amount' => 'required|numeric|min:0',
            'refund_deposit' => 'boolean',
            'refund_method' => 'nullable|string|in:cash,transfer,credit',
        ]);

        DB::beginTransaction();

        try {
            $accountingService = new AccountingService();
            $closureService = new ContractClosureService();

            // 1. Create final invoice
            $invoice = $this->createFinalInvoice($contract, $validated['invoice_items']);

            // 2. Apply Quick Pay advances to invoice (convert liabilities to revenue)
            $this->applyAdvancesToInvoice($invoice, $contract, $accountingService);

            // 3. Record revenue recognition
            $accountingService->recordInvoice($invoice);

            // 4. Handle security deposit refund if applicable
            if ($validated['refund_deposit'] ?? false) {
                $this->processDepositRefund($contract, $accountingService, $validated['refund_method'] ?? 'cash');
            }

            // 5. Complete contract if not already completed
            if ($contract->status !== 'completed') {
                $contract->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            DB::commit();

            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Contract closure finalized and invoice created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Contract closure finalization failed', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to finalize contract closure: ' . $e->getMessage()]);
        }
    }

    /**
     * Create final invoice with all line items.
     */
    private function createFinalInvoice(Contract $contract, array $invoiceItems): Invoice
    {
        $totalAmount = array_sum(array_column($invoiceItems, 'amount'));

        $invoice = Invoice::create([
            'contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => $totalAmount,
            'currency' => $contract->currency ?? 'AED',
            'status' => 'pending',
            'team_id' => $contract->team_id,
        ]);

        // Create invoice items
        foreach ($invoiceItems as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['amount'],
                'total_amount' => $item['amount'],
            ]);
        }

        return $invoice;
    }

    /**
     * Apply Quick Pay advances to invoice (convert liabilities to revenue).
     */
    private function applyAdvancesToInvoice(Invoice $invoice, Contract $contract, AccountingService $accountingService): void
    {
        $payments = $contract->paymentReceipts()
            ->with('allocations')
            ->get();

        foreach ($payments as $payment) {
            foreach ($payment->allocations as $allocation) {
                // Skip security deposit - handled separately
                if ($allocation->row_id === 'violation_guarantee') {
                    continue;
                }

                // Apply advance to invoice
                $accountingService->applyAdvanceToInvoice($invoice, $allocation);
            }
        }
    }

    /**
     * Process security deposit refund.
     */
    private function processDepositRefund(Contract $contract, AccountingService $accountingService, string $refundMethod): void
    {
        $depositAmount = $contract->paymentReceipts()
            ->with('allocations')
            ->get()
            ->flatMap->allocations
            ->where('row_id', 'violation_guarantee')
            ->sum('amount');

        if ($depositAmount > 0) {
            $accountingService->processDepositRefund($contract, $depositAmount, $refundMethod);
        }
    }

    /**
     * Generate unique invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        $lastInvoice = Invoice::where('team_id', Auth::user()->team_id)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastInvoice ? (int) substr($lastInvoice->invoice_number, 4) + 1 : 1;

        return 'INV-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
