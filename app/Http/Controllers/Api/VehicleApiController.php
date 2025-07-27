<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VehicleApiController extends Controller
{
    /**
     * Get all vehicles with their status
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get query parameters for filtering
            $status = $request->query('status');
            $location = $request->query('location');
            $search = $request->query('search');
            $perPage = min($request->query('per_page', 15), 100);
            $page = $request->query('page', 1);

            // Build query
            $query = Vehicle::with('location:id,name,city,country')
                ->select([
                    'id',
                    'plate_number',
                    'make',
                    'model',
                    'year',
                    'color',
                    'category',
                    'status',
                    'ownership_status',
                    'location_id',
                    'price_daily',
                    'price_weekly',
                    'price_monthly',
                    'is_active',
                    'created_at',
                    'updated_at'
                ]);

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($location) {
                $query->where('location_id', $location);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('make', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('plate_number', 'like', "%{$search}%");
                });
            }

            // Only show active vehicles by default
            $query->where('is_active', true);

            $vehicles = $query->orderBy('plate_number')->paginate($perPage, ['*'], 'page', $page);

            // Format the response
            $formattedVehicles = $vehicles->getCollection()->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'plate_number' => $vehicle->plate_number,
                    'vehicle_info' => [
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'color' => $vehicle->color,
                        'category' => $vehicle->category,
                    ],
                    'status' => $vehicle->status,
                    'ownership_status' => $vehicle->ownership_status,
                    'location' => $vehicle->location ? [
                        'id' => $vehicle->location->id,
                        'name' => $vehicle->location->name,
                        'city' => $vehicle->location->city,
                        'country' => $vehicle->location->country,
                    ] : null,
                    'pricing' => [
                        'daily' => $vehicle->price_daily,
                        'weekly' => $vehicle->price_weekly,
                        'monthly' => $vehicle->price_monthly,
                    ],
                    'is_active' => $vehicle->is_active,
                    'last_updated' => $vehicle->updated_at?->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedVehicles,
                'pagination' => [
                    'current_page' => $vehicles->currentPage(),
                    'last_page' => $vehicles->lastPage(),
                    'per_page' => $vehicles->perPage(),
                    'total' => $vehicles->total(),
                    'from' => $vehicles->firstItem(),
                    'to' => $vehicles->lastItem(),
                ],
                'filters' => [
                    'status' => $status,
                    'location' => $location,
                    'search' => $search,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching vehicles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new vehicle
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_number' => 'required|string|max:255|unique:vehicles,plate_number',
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
                'odometer' => 'required|integer|min:0',
                'chassis_number' => 'required|string|max:255|unique:vehicles,chassis_number',
                'license_expiry_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vehicle = Vehicle::create($request->all());
            $vehicle->load('location');

            return response()->json([
                'success' => true,
                'message' => 'Vehicle created successfully',
                'data' => $vehicle
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating vehicle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a vehicle
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $vehicle = Vehicle::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'plate_number' => 'sometimes|required|string|max:255|unique:vehicles,plate_number,' . $vehicle->id,
                'make' => 'sometimes|required|string|max:255',
                'model' => 'sometimes|required|string|max:255',
                'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
                'color' => 'sometimes|required|string|max:255',
                'seats' => 'nullable|integer|min:1|max:50',
                'doors' => 'nullable|integer|min:1|max:10',
                'category' => 'sometimes|required|string|max:255',
                'price_daily' => 'nullable|numeric|min:0',
                'price_weekly' => 'nullable|numeric|min:0',
                'price_monthly' => 'nullable|numeric|min:0',
                'location_id' => 'nullable|uuid|exists:locations,id',
                'status' => 'sometimes|required|in:available,rented,maintenance,out_of_service',
                'ownership_status' => 'sometimes|required|in:owned,borrowed',
                'odometer' => 'sometimes|required|integer|min:0',
                'chassis_number' => 'sometimes|required|string|max:255|unique:vehicles,chassis_number,' . $vehicle->id,
                'license_expiry_date' => 'sometimes|required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vehicle->update($request->all());
            $vehicle->load('location');

            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated successfully',
                'data' => $vehicle
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating vehicle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a vehicle
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $vehicle = Vehicle::findOrFail($id);
            $vehicle->delete();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting vehicle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search vehicles
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $searchQuery = $request->query;

            $vehicles = Vehicle::with('location')
                ->where('is_active', true)
                ->where(function ($q) use ($searchQuery) {
                    $q->where('make', 'like', "%{$searchQuery}%")
                      ->orWhere('model', 'like', "%{$searchQuery}%")
                      ->orWhere('plate_number', 'like', "%{$searchQuery}%")
                      ->orWhere('chassis_number', 'like', "%{$searchQuery}%");
                })
                ->orderBy('plate_number')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $vehicles,
                'query' => $searchQuery,
                'count' => $vehicles->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching vehicles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available vehicles
     */
    public function available(Request $request): JsonResponse
    {
        try {
            $vehicles = Vehicle::with('location')
                ->where('status', 'available')
                ->where('is_active', true)
                ->orderBy('plate_number')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $vehicles,
                'count' => $vehicles->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching available vehicles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific vehicle by ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $vehicle = Vehicle::with([
                'location:id,name,city,country',
                'contracts' => function($query) {
                    $query->latest()->limit(1)->select(['id', 'vehicle_id', 'status', 'start_date', 'end_date']);
                }
            ])->where('id', $id)
              ->where('is_active', true)
              ->first([
                  'id',
                  'plate_number',
                  'make',
                  'model',
                  'year',
                  'color',
                  'category',
                  'status',
                  'ownership_status',
                  'location_id',
                  'price_daily',
                  'price_weekly',
                  'price_monthly',
                  'odometer',
                  'license_expiry_date',
                  'insurance_expiry_date',
                  'is_active',
                  'created_at',
                  'updated_at'
              ]);

            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'error' => 'Vehicle not found',
                    'message' => 'The requested vehicle was not found or is inactive'
                ], 404);
            }

            $formattedVehicle = [
                'id' => $vehicle->id,
                'plate_number' => $vehicle->plate_number,
                'vehicle_info' => [
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'color' => $vehicle->color,
                    'category' => $vehicle->category,
                ],
                'status' => $vehicle->status,
                'ownership_status' => $vehicle->ownership_status,
                'location' => $vehicle->location ? [
                    'id' => $vehicle->location->id,
                    'name' => $vehicle->location->name,
                    'city' => $vehicle->location->city,
                    'country' => $vehicle->location->country,
                ] : null,
                'pricing' => [
                    'daily' => $vehicle->price_daily,
                    'weekly' => $vehicle->price_weekly,
                    'monthly' => $vehicle->price_monthly,
                ],
                'additional_info' => [
                    'odometer' => $vehicle->odometer,
                    'license_expiry' => $vehicle->license_expiry_date?->toDateString(),
                    'insurance_expiry' => $vehicle->insurance_expiry_date?->toDateString(),
                ],
                'current_contract' => $vehicle->contracts->first() ? [
                    'id' => $vehicle->contracts->first()->id,
                    'status' => $vehicle->contracts->first()->status,
                    'start_date' => $vehicle->contracts->first()->start_date?->toDateString(),
                    'end_date' => $vehicle->contracts->first()->end_date?->toDateString(),
                ] : null,
                'is_active' => $vehicle->is_active,
                'last_updated' => $vehicle->updated_at?->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedVehicle,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve vehicle',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
