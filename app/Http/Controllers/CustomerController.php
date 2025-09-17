<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Team;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers.
     */
    public function index(Request $request)
    {
        $teamId = auth()->user()->team_id;
        $search = $request->get('search', '');
        $filter = $request->get('filter', 'all'); // all, blocked, active

        // Build the query for paginated customers
        $query = Customer::with(['team', 'blockedBy'])
            ->where('team_id', $teamId);

        // Apply search filters if search term is provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('driver_name', 'like', "%{$search}%")
                  ->orWhere('trade_license_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('drivers_license_number', 'like', "%{$search}%")
                  ->orWhere('passport_number', 'like', "%{$search}%")
                  ->orWhere('resident_id_number', 'like', "%{$search}%")
                  ->orWhere('nationality', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$search}%"]);
            });
        }

        // Apply status filter
        if ($filter === 'blocked') {
            $query->where('is_blocked', true);
        } elseif ($filter === 'active') {
            $query->where('is_blocked', false)->where('status', 'active');
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(10);

        // Preserve search and filter parameters in pagination links
        $customers->appends(['search' => $search, 'filter' => $filter]);

        // Get statistics (need to query all for accurate stats)
        $allCustomers = Customer::where('team_id', $teamId)->get();
        $stats = [
            'total' => $allCustomers->count(),
            'active' => $allCustomers->where('status', 'active')->where('is_blocked', false)->count(),
            'blocked' => $allCustomers->where('is_blocked', true)->count(),
            'new_this_month' => $allCustomers->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'stats' => $stats,
            'search' => $search,
            'filter' => $filter,
        ]);
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer)
    {
        // Ensure customer belongs to user's team
        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        // Load relationships needed for the UI
        $customer->load(['blockedBy', 'contracts.vehicle', 'invoices', 'customerNotes.user']);

        // Determine open contract (prefer active, then draft)
        $openContract = $customer->contracts()
            ->whereIn('status', ['active', 'draft'])
            ->orderByRaw("FIELD(status, 'active','draft')")
            ->latest()
            ->first();

        // Previous contracts (completed/void)
        $previousContracts = $customer->contracts()
            ->whereIn('status', ['completed', 'void'])
            ->latest()
            ->limit(5)
            ->get();

        // Recent invoices
        $invoices = $customer->invoices()
            ->latest('invoice_date')
            ->limit(10)
            ->get();

        // Totals and VIP heuristic
        $totalContracts = $customer->contracts()->count();
        $totalInvoicedAmount = (float) $customer->invoices()->sum('total_amount');
        $isVip = $totalContracts >= 5 || $totalInvoicedAmount >= 50000;

        // Build timeline events
        $timeline = [];

        // Customer created event
        $timeline[] = [
            'id' => (string) $customer->id,
            'type' => 'customer_created',
            'title' => 'Customer created',
            'date' => optional($customer->created_at)->toISOString(),
            'status' => null,
            'link' => "/customers/{$customer->id}",
        ];

        // Block/unblock history
        $customer->loadMissing('blockHistory.performedBy');
        foreach ($customer->blockHistory as $entry) {
            $timeline[] = [
                'id' => (string) $entry->id,
                'type' => $entry->action === 'blocked' ? 'customer_blocked' : 'customer_unblocked',
                'title' => $entry->action === 'blocked' ? 'Customer blocked' : 'Customer unblocked',
                'date' => optional($entry->performed_at)->toISOString(),
                'status' => $entry->reason,
                'meta' => [
                    'by' => $entry->performedBy?->name,
                ],
                'link' => "/customers/{$customer->id}",
            ];
        }

        // Contracts events (created, activated, completed, voided)
        foreach ($customer->contracts as $contract) {
            // created
            $timeline[] = [
                'id' => (string) $contract->id . ':created',
                'type' => 'contract_created',
                'title' => 'Contract created ' . $contract->contract_number,
                'date' => optional($contract->created_at)->toISOString(),
                'status' => $contract->status,
                'link' => "/contracts/{$contract->id}",
            ];
            if ($contract->activated_at) {
                $timeline[] = [
                    'id' => (string) $contract->id . ':activated',
                    'type' => 'contract_activated',
                    'title' => 'Contract activated ' . $contract->contract_number,
                    'date' => optional($contract->activated_at)->toISOString(),
                    'status' => 'active',
                    'link' => "/contracts/{$contract->id}",
                ];
            }
            if ($contract->completed_at) {
                $timeline[] = [
                    'id' => (string) $contract->id . ':completed',
                    'type' => 'contract_completed',
                    'title' => 'Contract completed ' . $contract->contract_number,
                    'date' => optional($contract->completed_at)->toISOString(),
                    'status' => 'completed',
                    'link' => "/contracts/{$contract->id}",
                ];
            }
            if ($contract->voided_at) {
                $timeline[] = [
                    'id' => (string) $contract->id . ':voided',
                    'type' => 'contract_voided',
                    'title' => 'Contract voided ' . $contract->contract_number,
                    'date' => optional($contract->voided_at)->toISOString(),
                    'status' => 'void',
                    'link' => "/contracts/{$contract->id}",
                ];
            }
        }

        // Invoices created
        foreach ($customer->invoices as $invoice) {
            $timeline[] = [
                'id' => (string) $invoice->id,
                'type' => 'invoice_created',
                'title' => 'Invoice ' . $invoice->invoice_number,
                'date' => optional($invoice->invoice_date ?? $invoice->created_at)->toISOString(),
                'status' => $invoice->status,
                'link' => "/invoices/{$invoice->id}",
            ];
        }

        // Payments
        foreach ($customer->payments as $payment) {
            $timeline[] = [
                'id' => (string) $payment->id,
                'type' => 'payment_received',
                'title' => 'Payment received',
                'date' => optional($payment->payment_date ?? $payment->created_at)->toISOString(),
                'status' => (float) $payment->amount,
                'link' => $payment->invoice_id ? "/invoices/{$payment->invoice_id}" : null,
                'meta' => [
                    'method' => $payment->payment_method,
                ],
            ];
        }

        // Sort timeline desc by date
        usort($timeline, function ($a, $b) {
            return strcmp($b['date'] ?? '', $a['date'] ?? '');
        });

        return Inertia::render('Customers/Show', [
            'customer' => array_merge($customer->toArray(), [
                'drivers_license_url' => $customer->getFirstMedia('drivers_license') ? route('media.stream', $customer->getFirstMedia('drivers_license')->uuid) : null,
                'passport_url' => $customer->getFirstMedia('passport') ? route('media.stream', $customer->getFirstMedia('passport')->uuid) : null,
                'resident_id_url' => $customer->getFirstMedia('resident_id') ? route('media.stream', $customer->getFirstMedia('resident_id')->uuid) : null,
                'trade_license_url' => $customer->getFirstMedia('trade_license') ? route('media.stream', $customer->getFirstMedia('trade_license')->uuid) : null,
                'visit_visa_url' => $customer->getFirstMedia('visit_visa') ? route('media.stream', $customer->getFirstMedia('visit_visa')->uuid) : null,
            ]),
            'openContract' => $openContract ? [
                'id' => (string) $openContract->id,
                'contract_number' => $openContract->contract_number,
                'status' => $openContract->status,
                'start_date' => optional($openContract->start_date)->toISOString(),
                'end_date' => optional($openContract->end_date)->toISOString(),
                'vehicle' => $openContract->relationLoaded('vehicle') && $openContract->vehicle ? [
                    'id' => (string) $openContract->vehicle->id,
                    'make' => $openContract->vehicle->make,
                    'model' => $openContract->vehicle->model,
                    'plate_number' => $openContract->vehicle->plate_number,
                ] : null,
            ] : null,
            'previousContracts' => $previousContracts->map(function ($c) {
                return [
                    'id' => (string) $c->id,
                    'contract_number' => $c->contract_number,
                    'status' => $c->status,
                    'start_date' => optional($c->start_date)->toISOString(),
                    'end_date' => optional($c->end_date)->toISOString(),
                ];
            }),
            'invoices' => $invoices->map(function ($inv) {
                return [
                    'id' => (string) $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'status' => $inv->status,
                    'total_amount' => (float) $inv->total_amount,
                    'invoice_date' => optional($inv->invoice_date)->toISOString(),
                ];
            }),
            'totals' => [
                'contracts' => $totalContracts,
                'invoiced_amount' => $totalInvoicedAmount,
            ],
            'isVip' => $isVip,
            'timeline' => $timeline,
            'customerNotes' => ($customer->customerNotes ?? collect())->map(function ($note) {
                return [
                    'id' => (string) $note->id,
                    'content' => $note->content,
                    'created_at' => optional($note->created_at)->toISOString(),
                    'user' => $note->user ? [
                        'id' => (string) $note->user->id,
                        'name' => $note->user->name,
                    ] : null,
                ];
            }),
        ]);
    }

    public function addNote(Customer $customer, \Illuminate\Http\Request $request)
    {
        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $note = $customer->customerNotes()->create([
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'Note added');
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer)
    {
        // Ensure customer belongs to user's team
        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $validated = $request->validated();

        $validated['team_id'] = auth()->user()->team_id;

        // Create customer without raw files
        $customer = Customer::create(collect($validated)->except([
            'trade_license_pdf', 'visit_visa_pdf', 'passport_pdf', 'resident_id_pdf',
        ])->all());

        // Attach media to S3 collections if provided
        if ($request->hasFile('drivers_license_pdf')) {
            $customer->addMediaFromRequest('drivers_license_pdf')
                ->usingFileName('drivers_license_' . time() . '.' . $request->file('drivers_license_pdf')->getClientOriginalExtension())
                ->toMediaCollection('drivers_license', 's3');
        }

        if ($request->hasFile('trade_license_pdf')) {
            $customer->addMediaFromRequest('trade_license_pdf')
                ->usingFileName('trade_license_' . time() . '.' . $request->file('trade_license_pdf')->getClientOriginalExtension())
                ->toMediaCollection('trade_license', 's3');
        }

        if ($request->hasFile('visit_visa_pdf')) {
            $customer->addMediaFromRequest('visit_visa_pdf')
                ->usingFileName('visit_visa_' . time() . '.' . $request->file('visit_visa_pdf')->getClientOriginalExtension())
                ->toMediaCollection('visit_visa', 's3');
        }

        if ($request->hasFile('passport_pdf')) {
            $customer->addMediaFromRequest('passport_pdf')
                ->usingFileName('passport_' . time() . '.' . $request->file('passport_pdf')->getClientOriginalExtension())
                ->toMediaCollection('passport', 's3');
        }

        if ($request->hasFile('resident_id_pdf')) {
            $customer->addMediaFromRequest('resident_id_pdf')
                ->usingFileName('resident_id_' . time() . '.' . $request->file('resident_id_pdf')->getClientOriginalExtension())
                ->toMediaCollection('resident_id', 's3');
        }

        $customerData = [
            'id' => $customer->id,
            'label' => $customer->first_name . ' ' . $customer->last_name . ' - ' . $customer->phone,
            'value' => $customer->id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
        ];

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'customer' => $customerData
            ]);
        }

        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, '/contracts/create')) {
            return redirect('/contracts/create')->with([
                'success' => 'Customer created successfully.',
                'newCustomer' => $customerData
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'customer' => $customerData
            ]);
        }

        return redirect(route('customers.show', $customer))->with([
            'success' => 'Customer created successfully.',
            'customer' => $customerData
        ]);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $validated = $request->validated();

        // Update non-file fields only
        $customer->update(collect($validated)->except([
            'drivers_license_pdf', 'trade_license_pdf', 'visit_visa_pdf', 'passport_pdf', 'resident_id_pdf',
        ])->all());

        // Replace media if a new file is uploaded
        if ($request->hasFile('drivers_license_pdf')) {
            $customer->clearMediaCollection('drivers_license');
            $customer->addMediaFromRequest('drivers_license_pdf')
                ->usingFileName('drivers_license_' . time() . '.' . $request->file('drivers_license_pdf')->getClientOriginalExtension())
                ->toMediaCollection('drivers_license', 's3');
        }

        if ($request->hasFile('trade_license_pdf')) {
            $customer->clearMediaCollection('trade_license');
            $customer->addMediaFromRequest('trade_license_pdf')
                ->usingFileName('trade_license_' . time() . '.' . $request->file('trade_license_pdf')->getClientOriginalExtension())
                ->toMediaCollection('trade_license', 's3');
        }

        if ($request->hasFile('visit_visa_pdf')) {
            $customer->clearMediaCollection('visit_visa');
            $customer->addMediaFromRequest('visit_visa_pdf')
                ->usingFileName('visit_visa_' . time() . '.' . $request->file('visit_visa_pdf')->getClientOriginalExtension())
                ->toMediaCollection('visit_visa', 's3');
        }

        if ($request->hasFile('passport_pdf')) {
            $customer->clearMediaCollection('passport');
            $customer->addMediaFromRequest('passport_pdf')
                ->usingFileName('passport_' . time() . '.' . $request->file('passport_pdf')->getClientOriginalExtension())
                ->toMediaCollection('passport', 's3');
        }

        if ($request->hasFile('resident_id_pdf')) {
            $customer->clearMediaCollection('resident_id');
            $customer->addMediaFromRequest('resident_id_pdf')
                ->usingFileName('resident_id_' . time() . '.' . $request->file('resident_id_pdf')->getClientOriginalExtension())
                ->toMediaCollection('resident_id', 's3');
        }

        return redirect("/customers/{$customer->id}")->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer)
    {
        // Ensure customer belongs to user's team
        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $customer->delete();

        return back()->with('success', 'Customer deleted successfully.');
    }

    /**
     * Block a customer.
     */
    public function block(Customer $customer, Request $request)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        if ($customer->isBlocked()) {
            return back()->with('error', 'Customer is already blocked.');
        }

        $customer->block($request->reason, auth()->user(), $request->notes);

        return back()->with('success', 'Customer has been blocked successfully.');
    }

    /**
     * Unblock a customer.
     */
    public function unblock(Customer $customer, Request $request)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        if (!$customer->isBlocked()) {
            return back()->with('error', 'Customer is not blocked.');
        }

        $customer->unblock(auth()->user(), $request->notes);

        return back()->with('success', 'Customer has been unblocked successfully.');
    }

    /**
     * Show block history for a customer.
     */
    public function blockHistory(Customer $customer)
    {
        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $history = $customer->blockHistory()->with('performedBy')->paginate(10);

        return Inertia::render('Customers/BlockHistory', [
            'customer' => $customer,
            'history' => $history
        ]);
    }
}
