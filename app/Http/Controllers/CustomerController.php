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

        // Load the blocked_by relationship
        $customer->load('blockedBy');

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
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

        // Handle trade license PDF upload
        if ($request->hasFile('trade_license_pdf')) {
            $file = $request->file('trade_license_pdf');
            $filename = 'trade_license_' . time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('trade_licenses', $filename, 'public');
            $validated['trade_license_pdf_path'] = $path;
        }

        // Remove the file from validated data since it's not a database field
        unset($validated['trade_license_pdf']);

        $customer = Customer::create($validated);

        $customerData = [
            'id' => $customer->id,
            'label' => $customer->first_name . ' ' . $customer->last_name . ' - ' . $customer->phone,
            'value' => $customer->id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
        ];

        // If this is an AJAX request (from contract creation), return JSON
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'customer' => $customerData
            ]);
        }

        // Check if this request came from contracts/create page
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, '/contracts/create')) {
            // Return to contracts/create with customer data in props
            return redirect('/contracts/create')->with([
                'success' => 'Customer created successfully.',
                'newCustomer' => $customerData
            ]);
        }

        // For JSON requests, return the customer data directly
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'customer' => $customerData
            ]);
        }

        return redirect('/customers')->with([
            'success' => 'Customer created successfully.',
            'customer' => $customerData
        ]);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        // Ensure customer belongs to user's team
        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $validated = $request->validated();

        // Handle trade license PDF upload
        if ($request->hasFile('trade_license_pdf')) {
            // Delete old file if it exists
            if ($customer->trade_license_pdf_path && \Storage::disk('public')->exists($customer->trade_license_pdf_path)) {
                \Storage::disk('public')->delete($customer->trade_license_pdf_path);
            }

            $file = $request->file('trade_license_pdf');
            $filename = 'trade_license_' . time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('trade_licenses', $filename, 'public');
            $validated['trade_license_pdf_path'] = $path;
        }

        // Remove the file from validated data since it's not a database field
        unset($validated['trade_license_pdf']);

        $customer->update($validated);

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