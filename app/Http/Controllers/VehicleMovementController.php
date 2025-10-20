<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleMovement;
use App\Services\VehicleMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class VehicleMovementController extends Controller
{
    protected VehicleMovementService $movementService;

    public function __construct(VehicleMovementService $movementService)
    {
        $this->movementService = $movementService;
    }

    /**
     * Display a listing of vehicle movements.
     */
    public function index(Vehicle $vehicle): Response
    {
        $movements = $this->movementService->getMovementHistory($vehicle, 50);

        return Inertia::render('Vehicles/Movements', [
            'vehicle' => $vehicle,
            'movements' => $movements,
        ]);
    }

    /**
     * Store a new vehicle movement record.
     */
    public function store(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'event_type' => 'required|in:maintenance,inspection,relocation,manual_adjustment,other',
            'mileage' => 'required|integer|min:0',
            'fuel_level' => 'nullable|in:full,3/4,1/2,1/4,low,empty',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'notes' => 'nullable|string|max:2000',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:10240',
            'metadata' => 'nullable|array',
        ]);

        // Handle photo uploads
        $photosPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('vehicle_movements', 'public');
                $photosPaths[] = $path;
            }
        }

        try {
            $movement = $this->movementService->recordMovement(
                vehicle: $vehicle,
                eventType: $validated['event_type'],
                mileage: $validated['mileage'],
                fuelLevel: $validated['fuel_level'] ?? null,
                locationId: $validated['location_id'] ?? null,
                contractId: null,
                photos: $photosPaths,
                notes: $validated['notes'] ?? null,
                metadata: $validated['metadata'] ?? []
            );

            return back()->with('success', 'Vehicle movement recorded successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['mileage' => $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to record vehicle movement.'])->withInput();
        }
    }

    /**
     * Get movement history for a vehicle (API endpoint).
     */
    public function getHistory(Vehicle $vehicle)
    {
        $movements = $this->movementService->getMovementHistory($vehicle, 20);

        return response()->json([
            'movements' => $movements->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'event_type' => $movement->event_type,
                    'event_type_label' => $movement->event_type_label,
                    'mileage' => $movement->mileage,
                    'fuel_level' => $movement->fuel_level,
                    'notes' => $movement->notes,
                    'performed_at' => $movement->performed_at->toISOString(),
                    'performed_by' => $movement->performedBy ? [
                        'id' => $movement->performedBy->id,
                        'name' => $movement->performedBy->name,
                    ] : null,
                    'location' => $movement->location ? [
                        'id' => $movement->location->id,
                        'name' => $movement->location->name,
                    ] : null,
                ];
            }),
        ]);
    }
}

