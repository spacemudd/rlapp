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

        // Define allowed fields that will be saved to reservations table
        $allowedFields = [
            'customer_id',
            'vehicle_id',
            'rate',
            'pickup_date',
            'pickup_location',
            'return_date',
            'status',
            'reservation_date',
            'notes',
            'total_amount',
            'duration_days',
            'team_id',
        ];

        // Filter only allowed fields from request
        $reservationData = array_intersect_key($request->all(), array_flip($allowedFields));

        // Check if customer_id exists, if not use first customer or create mock data
        $customer = Customer::find($reservationData['customer_id']);
        if (!$customer) {
            $customer = Customer::first();
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No customers found in system. Please use a valid customer_id.',
                    'available_customers' => []
                ], 400);
            }
            $reservationData['customer_id'] = $customer->id;
        }

        // Check if vehicle_id exists, if not use first vehicle
        $vehicle = Vehicle::find($reservationData['vehicle_id']);
        if (!$vehicle) {
            $vehicle = Vehicle::first();
            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'No vehicles found in system. Please use a valid vehicle_id.',
                    'available_vehicles' => []
                ], 400);
            }
            $reservationData['vehicle_id'] = $vehicle->id;
        }

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
            // Create reservation with filtered data (only allowed fields)
            $reservation = Reservation::create($reservationData);

            // Load relationships
            $reservation->load(['customer', 'vehicle', 'team']);

            // Get extra fields that were sent but not saved
            $extraFields = array_diff_key($request->all(), array_flip($allowedFields));

            return response()->json([
                'success' => true,
                'message' => 'Custom reservation created successfully',
                'data' => $reservation,
                'saved_fields' => $reservationData,
                'ignored_fields' => $extraFields,
                'mappings' => [
                    'original_customer_id' => $request->customer_id,
                    'used_customer_id' => $reservationData['customer_id'],
                    'original_vehicle_id' => $request->vehicle_id,
                    'used_vehicle_id' => $reservationData['vehicle_id']
                ],
                'info' => 'Only reservation table fields are saved. Extra fields are ignored. Auto-expires in 5 minutes if status is pending.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reservation',
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
            'api_endpoint' => url('/api/v1/test/custom-reservation'),
            'method' => 'POST',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'note' => 'This is a test API endpoint that does not require authentication or CSRF token'
        ]);
    }

    /**
     * Get available IDs for testing
     *
     * @return JsonResponse
     */
    public function getAvailableIds(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Use these real IDs for testing',
            'available_customers' => Customer::take(5)->get(['id', 'name', 'email'])->map(function($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name ?: 'Customer',
                    'email' => $customer->email
                ];
            }),
            'available_vehicles' => Vehicle::take(5)->get(['id', 'make', 'model', 'year', 'plate_number', 'price_daily'])->map(function($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'name' => "{$vehicle->make} {$vehicle->model} ({$vehicle->year})",
                    'plate_number' => $vehicle->plate_number,
                    'daily_rate' => $vehicle->price_daily
                ];
            }),
            'usage_examples' => [
                'example_1_with_real_ids' => [
                    'customer_id' => Customer::first()?->id,
                    'vehicle_id' => Vehicle::first()?->id,
                    'pickup_date' => '2025-02-01 10:00:00',
                    'return_date' => '2025-02-05 18:00:00',
                    'rate' => 180.00,
                    'status' => 'pending',
                    'pickup_location' => 'Dubai Airport'
                ],
                'example_2_with_fake_ids' => [
                    'customer_id' => 'any-fake-id',
                    'vehicle_id' => 'any-fake-id',
                    'pickup_date' => '2025-02-10 14:00:00',
                    'return_date' => '2025-02-14 14:00:00',
                    'rate' => 200.00,
                    'status' => 'confirmed',
                    'pickup_location' => 'Cairo Airport',
                    'notes' => 'Fake IDs will be automatically mapped to real ones'
                ]
            ],
            'api_endpoints' => [
                'create_with_custom_data' => 'POST /api/v1/test/custom-reservation',
                'get_test_data' => 'GET /api/v1/test/data',
                'get_available_ids' => 'GET /api/v1/test/ids'
            ]
        ]);
    }
}
