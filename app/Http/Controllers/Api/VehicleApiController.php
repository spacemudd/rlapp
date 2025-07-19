<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            $limit = $request->query('limit', 100); // Default limit of 100
            
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
            
            // Only show active vehicles by default
            $query->where('is_active', true);
            
            // Apply limit
            if ($limit && $limit <= 1000) { // Max 1000 records
                $query->limit($limit);
            }
            
            $vehicles = $query->orderBy('plate_number')->get();
            
            // Format the response
            $formattedVehicles = $vehicles->map(function ($vehicle) {
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
                'meta' => [
                    'total' => $formattedVehicles->count(),
                    'filters' => [
                        'status' => $status,
                        'location' => $location,
                    ],
                    'available_statuses' => [
                        'available',
                        'rented',
                        'maintenance',
                        'out_of_service'
                    ],
                ],
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve vehicles',
                'message' => $e->getMessage()
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