<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Vehicle::query();

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('make', 'like', "%$search%")
                  ->orWhere('model', 'like', "%$search%")
                  ->orWhere('plate_number', 'like', "%$search%")
                  ->orWhere('chassis_number', 'like', "%$search%")
                  ->orWhere('color', 'like', "%$search%")
                  ->orWhere('current_location', 'like', "%$search%")
                  ->orWhere('category', 'like', "%$search%")
                  ;
            });
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Apply category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by make
        if ($request->has('make') && $request->make) {
            $query->where('make', $request->make);
        }

        // Filter by ownership status
        if ($request->has('ownership') && $request->ownership) {
            $query->where('ownership_status', $request->ownership);
        }

        $vehicles = $query->with('location')
                         ->orderBy('created_at', 'desc')
                         ->paginate(15);

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'category' => $request->category,
                'make' => $request->make,
                'ownership' => $request->ownership,
            ],
            'statuses' => ['available', 'rented', 'maintenance', 'out_of_service'],
            'categories' => Vehicle::distinct()->pluck('category')->filter()->values(),
            'makes' => Vehicle::distinct()->pluck('make')->filter()->values(),
            'ownershipStatuses' => ['owned', 'borrowed'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Vehicles/Create', [
            'locations' => Location::active()->orderBy('name')->get(['id', 'name', 'city', 'country']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|max:255|unique:vehicles',
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'required|string|max:255',
            'seats' => 'nullable|integer|min:1|max:50',
            'doors' => 'nullable|integer|min:1|max:10',
            'category' => 'required|string|max:255',
            'price_daily' => 'nullable|numeric|min:0',
            'price_weekly' => 'nullable|numeric|min:0',
            'price_monthly' => 'nullable|numeric|min:0',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'status' => 'required|in:available,rented,maintenance,out_of_service',
            'ownership_status' => 'required|in:owned,borrowed',
            'borrowed_from_office' => 'nullable|string|max:255|required_if:ownership_status,borrowed',
            'borrowing_terms' => 'nullable|string',
            'borrowing_start_date' => 'nullable|date|required_if:ownership_status,borrowed',
            'borrowing_end_date' => 'nullable|date|after_or_equal:borrowing_start_date',
            'borrowing_notes' => 'nullable|string',
            'odometer' => 'required|integer|min:0',
            'chassis_number' => 'required|string|max:255|unique:vehicles',
            'license_expiry_date' => 'required|date',
            'insurance_expiry_date' => 'required|date',
            'recent_note' => 'nullable|string',
        ]);

        Vehicle::create($validated);

        return redirect()->route('vehicles.index')
            ->with('success', 'Vehicle created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        $vehicle->load('location');
        
        return Inertia::render('Vehicles/Show', [
            'vehicle' => $vehicle,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        return Inertia::render('Vehicles/Edit', [
            'vehicle' => $vehicle,
            'locations' => Location::active()->orderBy('name')->get(['id', 'name', 'city', 'country']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|max:255|unique:vehicles,plate_number,' . $vehicle->id,
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'required|string|max:255',
            'seats' => 'nullable|integer|min:1|max:50',
            'doors' => 'nullable|integer|min:1|max:10',
            'category' => 'required|string|max:255',
            'price_daily' => 'nullable|numeric|min:0',
            'price_weekly' => 'nullable|numeric|min:0',
            'price_monthly' => 'nullable|numeric|min:0',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'status' => 'required|in:available,rented,maintenance,out_of_service',
            'ownership_status' => 'required|in:owned,borrowed',
            'borrowed_from_office' => 'nullable|string|max:255|required_if:ownership_status,borrowed',
            'borrowing_terms' => 'nullable|string',
            'borrowing_start_date' => 'nullable|date|required_if:ownership_status,borrowed',
            'borrowing_end_date' => 'nullable|date|after_or_equal:borrowing_start_date',
            'borrowing_notes' => 'nullable|string',
            'odometer' => 'required|integer|min:0',
            'chassis_number' => 'required|string|max:255|unique:vehicles,chassis_number,' . $vehicle->id,
            'license_expiry_date' => 'required|date',
            'insurance_expiry_date' => 'required|date',
            'recent_note' => 'nullable|string',
        ]);

        $vehicle->update($validated);

        return redirect()->route('vehicles.index')
            ->with('success', 'Vehicle updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        // Check if vehicle is referenced in any invoices
        if ($vehicle->invoices()->exists()) {
            return back()->with('error', 'Cannot delete vehicle that has invoices associated with it.');
        }

        $vehicle->delete();

        return redirect()->route('vehicles.index')
            ->with('success', 'Vehicle deleted successfully.');
    }

    /**
     * Disable the specified vehicle.
     */
    public function disable(Vehicle $vehicle)
    {
        $vehicle->update(['status' => 'out_of_service']);

        return back()->with('success', 'Vehicle disabled successfully.');
    }

    /**
     * Enable the specified vehicle.
     */
    public function enable(Vehicle $vehicle)
    {
        $vehicle->update(['status' => 'available']);

        return back()->with('success', 'Vehicle enabled successfully.');
    }
}
