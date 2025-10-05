<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Contract;
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

        $query = Reservation::with(['customer', 'vehicle', 'team'])
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

        // Set reservation source for web interface (agent using browser)
        $validated['reservation_source'] = Reservation::SOURCE_AGENT;

        $reservation = Reservation::create($validated);

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Reservation $reservation): Response
    {
        $reservation->load(['customer', 'vehicle.location', 'team']);

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

    /**
     * Check vehicle availability for specific dates
     */
    public function checkVehicleAvailability(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'pickup_date' => 'required|date',
            'return_date' => 'required|date|after:pickup_date',
        ]);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $pickupDate = \Carbon\Carbon::parse($validated['pickup_date']);
        $returnDate = \Carbon\Carbon::parse($validated['return_date']);

        // Check for active contracts that overlap with the requested dates
        $conflictingContracts = Contract::where('vehicle_id', $vehicle->id)
            ->where('status', 'active')
            ->where(function ($query) use ($pickupDate, $returnDate) {
                $query->whereBetween('start_date', [$pickupDate, $returnDate])
                    ->orWhereBetween('end_date', [$pickupDate, $returnDate])
                    ->orWhere(function ($q) use ($pickupDate, $returnDate) {
                        $q->where('start_date', '<=', $pickupDate)
                          ->where('end_date', '>=', $returnDate);
                    });
            })
            ->with('customer')
            ->get();

        // Check for confirmed reservations that overlap
        $conflictingReservations = Reservation::where('vehicle_id', $vehicle->id)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($pickupDate, $returnDate) {
                $query->whereBetween('pickup_date', [$pickupDate, $returnDate])
                    ->orWhereBetween('return_date', [$pickupDate, $returnDate])
                    ->orWhere(function ($q) use ($pickupDate, $returnDate) {
                        $q->where('pickup_date', '<=', $pickupDate)
                          ->where('return_date', '>=', $returnDate);
                    });
            })
            ->with('customer')
            ->get();

        $hasConflict = $conflictingContracts->isNotEmpty() || $conflictingReservations->isNotEmpty();
        
        $conflicts = collect();
        
        // Format contract conflicts
        foreach ($conflictingContracts as $contract) {
            $conflicts->push([
                'type' => 'contract',
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'customer_name' => $contract->customer->first_name . ' ' . $contract->customer->last_name,
                'start_date' => $contract->start_date->format('d/m/Y H:i'),
                'end_date' => $contract->end_date->format('d/m/Y H:i'),
            ]);
        }

        // Format reservation conflicts
        foreach ($conflictingReservations as $reservation) {
            $conflicts->push([
                'type' => 'reservation',
                'id' => $reservation->id,
                'contract_number' => $reservation->uid,
                'customer_name' => $reservation->customer->first_name . ' ' . $reservation->customer->last_name,
                'start_date' => $reservation->pickup_date->format('d/m/Y H:i'),
                'end_date' => $reservation->return_date->format('d/m/Y H:i'),
            ]);
        }

        return response()->json([
            'available' => !$hasConflict,
            'conflicts' => $conflicts,
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model,
                'plate_number' => $vehicle->plate_number,
            ]
        ]);
    }

    /**
     * Search vehicles with availability status for specific dates
     */
    public function searchVehiclesWithAvailability(Request $request)
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'pickup_date' => 'required|date',
            'return_date' => 'required|date|after:pickup_date',
        ]);

        $query = $validated['query'] ?? '';
        $pickupDate = \Carbon\Carbon::parse($validated['pickup_date']);
        $returnDate = \Carbon\Carbon::parse($validated['return_date']);

        $vehicles = Vehicle::with([
            'vehicleMake',
            'vehicleModel',
            'contracts' => function ($q) {
                $q->where('status', 'active')->latest();
            }, 
            'reservations' => function ($q) {
                $q->where('status', 'confirmed')->latest();
            }])
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                if ($query) {
                    $q->where('plate_number', 'like', "%{$query}%")
                        ->orWhereHas('vehicleMake', function ($q) use ($query) {
                            $q->where('name_en', 'like', "%{$query}%")
                              ->orWhere('name_ar', 'like', "%{$query}%");
                        })
                        ->orWhereHas('vehicleModel', function ($q) use ($query) {
                            $q->where('name_en', 'like', "%{$query}%")
                              ->orWhere('name_ar', 'like', "%{$query}%");
                        });
                }
            })
            ->orderBy('make')
            ->orderBy('model')
            ->limit(50)
            ->get()
            ->map(function ($vehicle) use ($pickupDate, $returnDate) {
                // Check for conflicts
                $contractConflicts = $vehicle->contracts->filter(function ($contract) use ($pickupDate, $returnDate) {
                    return $contract->start_date <= $returnDate && $contract->end_date >= $pickupDate;
                });

                $reservationConflicts = $vehicle->reservations->filter(function ($reservation) use ($pickupDate, $returnDate) {
                    return $reservation->pickup_date <= $returnDate && $reservation->return_date >= $pickupDate;
                });

                $hasConflict = $contractConflicts->isNotEmpty() || $reservationConflicts->isNotEmpty();
                
                $conflictDetails = null;
                if ($hasConflict) {
                    $conflict = $contractConflicts->first() ?? $reservationConflicts->first();
                    $conflictDetails = [
                        'type' => $contractConflicts->isNotEmpty() ? 'contract' : 'reservation',
                        'contract_number' => $contractConflicts->isNotEmpty() ? $conflict->contract_number : $conflict->uid,
                        'customer_name' => $conflict->customer->first_name . ' ' . $conflict->customer->last_name,
                        'start_date' => $conflict->start_date->format('d/m/Y H:i'),
                        'end_date' => $conflict->end_date->format('d/m/Y H:i'),
                    ];
                }

                return [
                    'id' => $vehicle->id,
                    'label' => $vehicle->year . ' ' . $vehicle->make_name . ' ' . $vehicle->model_name . ' - ' . $vehicle->plate_number,
                    'value' => $vehicle->id,
                    'make' => $vehicle->make_name,
                    'model' => $vehicle->model_name,
                    'year' => $vehicle->year,
                    'plate_number' => $vehicle->plate_number,
                    'price_daily' => $vehicle->price_daily,
                    'price_weekly' => $vehicle->price_weekly,
                    'price_monthly' => $vehicle->price_monthly,
                    'availability' => $hasConflict ? 'unavailable' : 'available',
                    'conflict' => $conflictDetails,
                    'disabled' => $hasConflict,
                ];
            });

        return response()->json($vehicles);
    }

    /**
     * Get similar available vehicles when one is unavailable
     */
    public function getSimilarVehicles(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'pickup_date' => 'required|date',
            'return_date' => 'required|date|after:pickup_date',
        ]);

        $originalVehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $pickupDate = \Carbon\Carbon::parse($validated['pickup_date']);
        $returnDate = \Carbon\Carbon::parse($validated['return_date']);

        // Find similar vehicles (same make or category)
        $similarVehicles = Vehicle::with(['contracts' => function ($q) {
                $q->where('status', 'active')->latest();
            }, 'reservations' => function ($q) {
                $q->where('status', 'confirmed')->latest();
            }])
            ->where('is_active', true)
            ->where('id', '!=', $originalVehicle->id)
            ->where(function ($q) use ($originalVehicle) {
                $q->where('make', $originalVehicle->make)
                    ->orWhere('category', $originalVehicle->category);
            })
            ->orderBy('make')
            ->orderBy('model')
            ->limit(5)
            ->get()
            ->map(function ($vehicle) use ($pickupDate, $returnDate) {
                // Check for conflicts
                $contractConflicts = $vehicle->contracts->filter(function ($contract) use ($pickupDate, $returnDate) {
                    return $contract->start_date <= $returnDate && $contract->end_date >= $pickupDate;
                });

                $reservationConflicts = $vehicle->reservations->filter(function ($reservation) use ($pickupDate, $returnDate) {
                    return $reservation->pickup_date <= $returnDate && $reservation->return_date >= $pickupDate;
                });

                $hasConflict = $contractConflicts->isNotEmpty() || $reservationConflicts->isNotEmpty();

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
                    'availability' => $hasConflict ? 'unavailable' : 'available',
                    'disabled' => $hasConflict,
                ];
            })
            ->filter(function ($vehicle) {
                return $vehicle['availability'] === 'available';
            });

        return response()->json($similarVehicles->values());
    }
}
