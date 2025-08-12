<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $filter = $request->get('filter', 'all');

        $query = Reservation::with(['customer:id,first_name,last_name,email,phone', 'vehicle', 'team'])
            ->where('team_id', Auth::user()->team_id);

        // Apply filters based on the selected tab
        switch ($filter) {
            case 'today':
                $query->today();
                break;
            case 'tomorrow':
                $query->tomorrow();
                break;
            case 'pending':
                $query->pending();
                break;
            case 'confirmed':
                $query->confirmed();
                break;
            case 'completed':
                $query->completed();
                break;
            case 'canceled':
                $query->canceled();
                break;
            case 'expired':
                $query->expired();
                break;
        }

        $reservations = $query->orderBy('pickup_date', 'desc')->get();

        // Get statistics for tabs
        $stats = [
            'all' => Reservation::where('team_id', Auth::user()->team_id)->count(),
            'today' => Reservation::where('team_id', Auth::user()->team_id)->today()->count(),
            'tomorrow' => Reservation::where('team_id', Auth::user()->team_id)->tomorrow()->count(),
            'pending' => Reservation::where('team_id', Auth::user()->team_id)->pending()->count(),
            'confirmed' => Reservation::where('team_id', Auth::user()->team_id)->confirmed()->count(),
            'completed' => Reservation::where('team_id', Auth::user()->team_id)->completed()->count(),
            'canceled' => Reservation::where('team_id', Auth::user()->team_id)->canceled()->count(),
            'expired' => Reservation::where('team_id', Auth::user()->team_id)->expired()->count(),
        ];

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'stats' => $stats,
            'currentFilter' => $filter,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $customers = Customer::where('team_id', Auth::user()->team_id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        $vehicles = Vehicle::where('is_active', true)
            ->with('location')
            ->orderBy('make')
            ->get(['id', 'make', 'model', 'year', 'plate_number', 'price_daily', 'location_id']);

        return Inertia::render('Reservations/Create', [
            'customers' => $customers,
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'pickup_date' => 'required|date|after_or_equal:today',
            'pickup_location' => 'required|string|max:255',
            'return_date' => 'required|date|after:pickup_date',
            'rate' => 'required|numeric|min:0',
            'status' => 'required|in:pending,confirmed,completed,canceled',
            'notes' => 'nullable|string',
        ]);

        // Get the vehicle to set the rate if not provided
        if (!isset($validated['rate'])) {
            $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
            $validated['rate'] = $vehicle->price_daily;
        }

        $reservation = Reservation::create($validated);

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Reservation $reservation): Response
    {
        $reservation->load(['customer:id,first_name,last_name,email,phone', 'vehicle.location', 'team']);

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Reservation $reservation): Response
    {
        $customers = Customer::where('team_id', Auth::user()->team_id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        $vehicles = Vehicle::where('is_active', true)
            ->with('location')
            ->orderBy('make')
            ->get(['id', 'make', 'model', 'year', 'plate_number', 'price_daily', 'location_id']);

        $reservation->load(['customer', 'vehicle']);

        return Inertia::render('Reservations/Edit', [
            'reservation' => $reservation,
            'customers' => $customers,
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'pickup_date' => 'required|date',
            'pickup_location' => 'required|string|max:255',
            'return_date' => 'required|date|after:pickup_date',
            'rate' => 'required|numeric|min:0',
            'status' => 'required|in:pending,confirmed,completed,canceled',
            'notes' => 'nullable|string',
        ]);

        $reservation->update($validated);

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Reservation $reservation): RedirectResponse
    {
        $reservation->delete();

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation deleted successfully.');
    }

    /**
     * Update reservation status
     */
    public function updateStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,canceled',
        ]);

        $reservation->update(['status' => $validated['status']]);

        return redirect()->back()
            ->with('success', 'Reservation status updated successfully.');
    }
}
