<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RecentVehicleSelection;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecentVehicleController extends Controller
{
    /**
     * Get the recent vehicle selections for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $recentSelections = RecentVehicleSelection::getRecentForUser($user->id);

        $vehicles = $recentSelections->map(function ($selection) {
            $vehicle = $selection->vehicle;
            
            if (!$vehicle) {
                return null;
            }

            return [
                'id' => $vehicle->id,
                'value' => (string) $vehicle->id,
                'label' => $vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'plate_number' => $vehicle->plate_number,
                'price_daily' => $vehicle->price_daily,
                'price_weekly' => $vehicle->price_weekly,
                'price_monthly' => $vehicle->price_monthly,
            ];
        })->filter()->values();

        return response()->json($vehicles);
    }

    /**
     * Record a vehicle selection for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
        ]);

        try {
            RecentVehicleSelection::recordSelection($user->id, $validated['vehicle_id']);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle selection recorded successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record vehicle selection',
            ], 500);
        }
    }
}

