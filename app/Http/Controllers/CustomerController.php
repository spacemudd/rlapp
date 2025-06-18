<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Team;
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
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:customers',
            'phone' => 'required|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'drivers_license_number' => 'required|string|max:255',
            'drivers_license_expiry' => 'required|date|after:today',
            'country' => 'required|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

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
    public function update(Request $request, Customer $customer)
    {
        // Ensure customer belongs to user's team
        if ($customer->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:customers,email,' . $customer->id,
            'phone' => 'required|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'drivers_license_number' => 'required|string|max:255',
            'drivers_license_expiry' => 'required|date|after:today',
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

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