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
        
        // Build the query for paginated customers
        $query = Customer::with('team')
            ->where('team_id', $teamId);
        
        // Apply search filters if search term is provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
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
        
        $customers = $query->orderBy('created_at', 'desc')->paginate(10);
        
        // Preserve search parameter in pagination links
        $customers->appends(['search' => $search]);

        // Get statistics (need to query all for accurate stats)
        $allCustomers = Customer::where('team_id', $teamId)->get();
        $stats = [
            'total' => $allCustomers->count(),
            'active' => $allCustomers->where('status', 'active')->count(),
            'new_this_month' => $allCustomers->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return Inertia::render('Customers', [
            'customers' => $customers,
            'stats' => $stats,
            'search' => $search,
        ]);
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $validated = $request->validated();

        $validated['team_id'] = auth()->user()->team_id;

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

        return back()->with([
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

        $customer->update($validated);

        return back()->with('success', 'Customer updated successfully.');
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
} 