<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TestReservationApiController extends Controller
{
    /**
     * Create a new reservation for testing purposes (no auth required)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'pickup_date' => 'required|date',
            'pickup_location' => 'required|string|max:255',
            'return_date' => 'required|date|after:pickup_date',
            'rate' => 'required|numeric|min:0',
            'status' => 'required|in:pending,confirmed,completed,canceled,expired',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if vehicle is available for the requested period
        $conflictingReservations = Reservation::where('vehicle_id', $request->vehicle_id)
            ->where('status', '!=', 'canceled')
            ->where(function ($query) use ($request) {
                $query->whereBetween('pickup_date', [$request->pickup_date, $request->return_date])
                      ->orWhereBetween('return_date', [$request->pickup_date, $request->return_date])
                      ->orWhere(function ($q) use ($request) {
                          $q->where('pickup_date', '<=', $request->pickup_date)
                            ->where('return_date', '>=', $request->return_date);
                      });
            })
            ->exists();

        if ($conflictingReservations) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle is not available for the selected period'
            ], 409);
        }

        $reservationData = $request->all();

        // Use the first team for testing purposes
        $firstTeam = Team::first();
        if (!$firstTeam) {
            return response()->json([
                'success' => false,
                'message' => 'No team found in the system'
            ], 500);
        }

        $reservationData['team_id'] = $firstTeam->id;

        try {
            $reservation = Reservation::create($reservationData);
            $reservation->load(['customer', 'vehicle', 'team']);

            return response()->json([
                'success' => true,
                'message' => 'Test reservation created successfully',
                'data' => $reservation,
                'info' => 'This reservation will auto-expire in 5 minutes if status remains pending'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create reservation with custom data (accepts any data you want)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createCustom(Request $request): JsonResponse
    {
        // Minimal validation - only required fields
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required',
            'vehicle_id' => 'required',
            'pickup_date' => 'required',
            'return_date' => 'required',
            'rate' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required fields',
                'required_fields' => ['customer_id', 'vehicle_id', 'pickup_date', 'return_date', 'rate'],
                'errors' => $validator->errors()
            ], 422);
        }

        // Get all request data
        $reservationData = $request->all();

        // Set defaults for missing fields
        $reservationData['pickup_location'] = $reservationData['pickup_location'] ?? 'Not specified';
        $reservationData['status'] = $reservationData['status'] ?? 'pending';
        $reservationData['notes'] = $reservationData['notes'] ?? '';

        // Use the first team for testing purposes
        $firstTeam = Team::first();
        if ($firstTeam) {
            $reservationData['team_id'] = $firstTeam->id;
        }

        try {
            // Create reservation with your custom data
            $reservation = Reservation::create($reservationData);

            // Try to load relationships (will be null if IDs don't exist)
            $reservation->load(['customer', 'vehicle', 'team']);

            return response()->json([
                'success' => true,
                'message' => 'Custom reservation created successfully with your data',
                'data' => $reservation,
                'input_data' => $request->all(),
                'info' => 'This API accepts any data you provide. Auto-expires in 5 minutes if status is pending.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reservation with your data',
                'error' => $e->getMessage(),
                'input_data' => $request->all()
            ], 500);
        }
    }

    /**
     * Get all reservations for testing
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['customer', 'vehicle', 'team']);

        // Apply status filter if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $reservations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
            'count' => $reservations->count(),
            'message' => 'Test API - showing all reservations'
        ]);
    }

    /**
     * Get test data for API testing
     *
     * @return JsonResponse
     */
    public function testData(): JsonResponse
    {
        $customer = Customer::first();
        $vehicle = Vehicle::first();

        return response()->json([
            'success' => true,
            'customers' => Customer::take(3)->get(['id', 'name', 'email', 'phone']),
            'vehicles' => Vehicle::take(3)->get(['id', 'make', 'model', 'year', 'plate_number', 'price_daily']),
            'sample_request_body' => [
                'customer_id' => $customer?->id,
                'vehicle_id' => $vehicle?->id,
                'pickup_date' => now()->addDay()->format('Y-m-d H:i:s'),
                'pickup_location' => 'Dubai Airport',
                'return_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'rate' => $vehicle?->price_daily ?? 150.00,
                'status' => 'pending',
                'notes' => 'Test reservation from Postman API'
            ],
            'api_endpoint' => url('/api/v1/test/reservations'),
            'method' => 'POST',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'note' => 'This is a test API endpoint that does not require authentication or CSRF token'
        ]);
    }
}
