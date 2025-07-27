<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ReservationApiController extends Controller
{
    public function __construct()
    {
        // Suppress deprecation warnings for IFRS library
        error_reporting(E_ALL & ~E_DEPRECATED);
    }

    /**
     * Get team ID from authenticated user or request header
     */
    private function getTeamId(Request $request): string
    {
        return Auth::check() ? Auth::user()->team_id : $request->header('X-TEAM-ID', '01978391-2b82-7226-bc6a-e8e49a90c7f8');
    }

    /**
     * Display a listing of the reservations
     */
    public function index(Request $request): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $query = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $teamId);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->has('pickup_date_from')) {
            $query->whereDate('pickup_date', '>=', $request->pickup_date_from);
        }

        if ($request->has('pickup_date_to')) {
            $query->whereDate('pickup_date', '<=', $request->pickup_date_to);
        }

        if ($request->has('return_date_from')) {
            $query->whereDate('return_date', '>=', $request->return_date_from);
        }

        if ($request->has('return_date_to')) {
            $query->whereDate('return_date', '<=', $request->return_date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uid', 'like', "%{$search}%")
                  ->orWhere('pickup_location', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%")
                                   ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                      $vehicleQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('plate_number', 'like', "%{$search}%")
                                  ->orWhere('model', 'like', "%{$search}%");
                  });
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'pickup_date');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortFields = ['pickup_date', 'return_date', 'reservation_date', 'status', 'rate', 'total_amount', 'uid'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $reservations = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reservations->items(),
            'pagination' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
                'from' => $reservations->firstItem(),
                'to' => $reservations->lastItem(),
            ],
            'filters' => [
                'status' => $request->status,
                'customer_id' => $request->customer_id,
                'vehicle_id' => $request->vehicle_id,
                'pickup_date_from' => $request->pickup_date_from,
                'pickup_date_to' => $request->pickup_date_to,
                'return_date_from' => $request->return_date_from,
                'return_date_to' => $request->return_date_to,
                'search' => $request->search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]
        ]);
    }

    /**
     * Get a specific reservation by ID
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $reservation = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $teamId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $reservation
        ]);
    }

    /**
     * Create a new reservation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'pickup_date' => 'required|date|after:now',
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
        $reservationData['team_id'] = $this->getTeamId($request);

        $reservation = Reservation::create($reservationData);
        $reservation->load(['customer', 'vehicle', 'team']);

        return response()->json([
            'success' => true,
            'message' => 'Reservation created successfully',
            'data' => $reservation
        ], 201);
    }

    /**
     * Update an existing reservation
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $reservation = Reservation::where('team_id', $teamId)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'customer_id' => 'sometimes|required|exists:customers,id',
            'vehicle_id' => 'sometimes|required|exists:vehicles,id',
            'pickup_date' => 'sometimes|required|date',
            'pickup_location' => 'sometimes|required|string|max:255',
            'return_date' => 'sometimes|required|date|after:pickup_date',
            'rate' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:pending,confirmed,completed,canceled,expired',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check vehicle availability if dates or vehicle changed
        if ($request->has('vehicle_id') || $request->has('pickup_date') || $request->has('return_date')) {
            $vehicleId = $request->get('vehicle_id', $reservation->vehicle_id);
            $pickupDate = $request->get('pickup_date', $reservation->pickup_date);
            $returnDate = $request->get('return_date', $reservation->return_date);

            $conflictingReservations = Reservation::where('vehicle_id', $vehicleId)
                ->where('id', '!=', $id)
                ->where('status', '!=', 'canceled')
                ->where(function ($query) use ($pickupDate, $returnDate) {
                    $query->whereBetween('pickup_date', [$pickupDate, $returnDate])
                          ->orWhereBetween('return_date', [$pickupDate, $returnDate])
                          ->orWhere(function ($q) use ($pickupDate, $returnDate) {
                              $q->where('pickup_date', '<=', $pickupDate)
                                ->where('return_date', '>=', $returnDate);
                          });
                })
                ->exists();

            if ($conflictingReservations) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle is not available for the selected period'
                ], 409);
            }
        }

        $reservation->update($request->all());
        $reservation->load(['customer', 'vehicle', 'team']);

        return response()->json([
            'success' => true,
            'message' => 'Reservation updated successfully',
            'data' => $reservation
        ]);
    }

    /**
     * Delete a reservation
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $reservation = Reservation::where('team_id', $teamId)
            ->findOrFail($id);

        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reservation deleted successfully'
        ]);
    }

    /**
     * Update reservation status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $reservation = Reservation::where('team_id', $teamId)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,completed,canceled,expired',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $reservation->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Reservation status updated successfully',
            'data' => $reservation->load(['customer', 'vehicle', 'team'])
        ]);
    }

    /**
     * Get reservations by status
     */
    public function byStatus(Request $request, string $status): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $validStatuses = ['pending', 'confirmed', 'completed', 'canceled', 'expired'];

        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses)
            ], 400);
        }

        $query = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $teamId)
            ->where('status', $status);

        // Apply same filters as index method
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->has('pickup_date_from')) {
            $query->where('pickup_date', '>=', $request->pickup_date_from);
        }

        if ($request->has('pickup_date_to')) {
            $query->where('pickup_date', '<=', $request->pickup_date_to);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $reservations = $query->orderBy('pickup_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reservations->items(),
            'pagination' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
            ],
            'status' => $status
        ]);
    }

    /**
     * Get today's reservations
     */
    public function today(Request $request): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $reservations = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $teamId)
            ->today()
            ->orderBy('pickup_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
            'count' => $reservations->count()
        ]);
    }

    /**
     * Get tomorrow's reservations
     */
    public function tomorrow(Request $request): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $reservations = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $teamId)
            ->tomorrow()
            ->orderBy('pickup_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
            'count' => $reservations->count()
        ]);
    }

    /**
     * Get reservation statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $teamId = $this->getTeamId($request);

        $stats = [
            'total' => Reservation::where('team_id', $teamId)->count(),
            'pending' => Reservation::where('team_id', $teamId)->pending()->count(),
            'confirmed' => Reservation::where('team_id', $teamId)->confirmed()->count(),
            'completed' => Reservation::where('team_id', $teamId)->completed()->count(),
            'canceled' => Reservation::where('team_id', $teamId)->canceled()->count(),
            'expired' => Reservation::where('team_id', $teamId)->expired()->count(),
            'today' => Reservation::where('team_id', $teamId)->today()->count(),
            'tomorrow' => Reservation::where('team_id', $teamId)->tomorrow()->count(),
            'this_week' => Reservation::where('team_id', $teamId)
                ->whereBetween('pickup_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'this_month' => Reservation::where('team_id', $teamId)
                ->whereMonth('pickup_date', now()->month)
                ->whereYear('pickup_date', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get available vehicles for a given period
     */
    public function availableVehicles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pickup_date' => 'required|date',
            'return_date' => 'required|date|after:pickup_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $pickupDate = $request->pickup_date;
        $returnDate = $request->return_date;

        // Get all vehicles
        $allVehicles = Vehicle::where('status', 'available')
            ->where('is_active', true)
            ->get();

        // Get vehicles that have conflicting reservations
        $busyVehicleIds = Reservation::where('status', '!=', 'canceled')
            ->where(function ($query) use ($pickupDate, $returnDate) {
                $query->whereBetween('pickup_date', [$pickupDate, $returnDate])
                      ->orWhereBetween('return_date', [$pickupDate, $returnDate])
                      ->orWhere(function ($q) use ($pickupDate, $returnDate) {
                          $q->where('pickup_date', '<=', $pickupDate)
                            ->where('return_date', '>=', $returnDate);
                      });
            })
            ->pluck('vehicle_id')
            ->unique();

        // Filter out busy vehicles
        $availableVehicles = $allVehicles->whereNotIn('id', $busyVehicleIds);

        return response()->json([
            'success' => true,
            'data' => $availableVehicles->values(),
            'count' => $availableVehicles->count(),
            'period' => [
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
            ]
        ]);
    }

    /**
     * Search reservations
     */
    public function search(Request $request): JsonResponse
    {
        $teamId = $this->getTeamId($request);

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

        $reservations = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $teamId)
            ->where(function ($query) use ($searchQuery) {
                $query->where('uid', 'like', "%{$searchQuery}%")
                      ->orWhere('pickup_location', 'like', "%{$searchQuery}%")
                      ->orWhere('notes', 'like', "%{$searchQuery}%")
                      ->orWhereHas('customer', function ($q) use ($searchQuery) {
                          $q->where('first_name', 'like', "%{$searchQuery}%")
                            ->orWhere('last_name', 'like', "%{$searchQuery}%")
                            ->orWhere('email', 'like', "%{$searchQuery}%")
                            ->orWhere('phone', 'like', "%{$searchQuery}%");
                      })
                      ->orWhereHas('vehicle', function ($q) use ($searchQuery) {
                          $q->where('plate_number', 'like', "%{$searchQuery}%")
                            ->orWhere('make', 'like', "%{$searchQuery}%")
                            ->orWhere('model', 'like', "%{$searchQuery}%");
                      });
            })
            ->orderBy('pickup_date', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
            'query' => $searchQuery,
            'count' => $reservations->count()
        ]);
    }
}
