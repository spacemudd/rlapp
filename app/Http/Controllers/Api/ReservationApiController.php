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
    /**
     * Update reservation status by UID (API-Key protected)
     *
     * Expected: X-API-KEY header, and JSON body with { "status": "..." }
     */
    public function updateStatusByUid(Request $request, string $uid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,completed,canceled,expired',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $reservation = Reservation::where('uid', $uid)->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation not found',
            ], 404);
        }

        $reservation->update(['status' => $request->string('status')->toString()]);
        $reservation->load(['customer', 'vehicle', 'team']);

        return response()->json([
            'success' => true,
            'message' => 'Reservation status updated successfully',
            'data' => $reservation,
        ]);
    }
    /**
     * Get all reservations with filtering and pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $query = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $user->team_id);

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
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservation = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $user->team_id)
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

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservationData = $request->all();
        $reservationData['team_id'] = $user->team_id;
        $reservationData['reservation_source'] = Reservation::SOURCE_WEB;

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
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservation = Reservation::where('team_id', $user->team_id)
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
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservation = Reservation::where('team_id', $user->team_id)
            ->findOrFail($id);

        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reservation deleted successfully'
        ]);
    }

    /**
     * Update reservation status
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,completed,canceled,expired'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservation = Reservation::where('team_id', $user->team_id)
            ->findOrFail($id);

        $reservation->update(['status' => $request->status]);
        $reservation->load(['customer', 'vehicle', 'team']);

        return response()->json([
            'success' => true,
            'message' => 'Reservation status updated successfully',
            'data' => $reservation
        ]);
    }

    /**
     * Change reservation status only (مخصص لتغيير الحالة فقط)
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,completed,canceled,expired'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservation = Reservation::where('team_id', $user->team_id)
            ->findOrFail($id);

        $oldStatus = $reservation->status;
        $newStatus = $request->status;

        // تحديث الحالة فقط
        $reservation->update(['status' => $newStatus]);
        $reservation->load(['customer', 'vehicle', 'team']);

        return response()->json([
            'success' => true,
            'message' => "Reservation status changed from {$oldStatus} to {$newStatus}",
            'data' => [
                'id' => $reservation->id,
                'uid' => $reservation->uid,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'status_changed_at' => now()->toISOString(),
                'reservation' => $reservation
            ]
        ]);
    }

    /**
     * Get pending reservations only
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $query = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $user->team_id)
            ->where('status', 'pending');

        // Apply additional filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uid', 'like', "%{$search}%")
                  ->orWhere('pickup_location', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                      $vehicleQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('plate_number', 'like', "%{$search}%");
                  });
            });
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
            'status' => 'pending'
        ]);
    }

    /**
     * Get reservations by status
     *
     * @param Request $request
     * @param string $status
     * @return JsonResponse
     */
    public function byStatus(Request $request, string $status): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $allowedStatuses = ['pending', 'confirmed', 'completed', 'canceled', 'expired'];

        if (!in_array($status, $allowedStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status'
            ], 400);
        }

        $query = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $user->team_id)
            ->where('status', $status);

        // Apply additional filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uid', 'like', "%{$search}%")
                  ->orWhere('pickup_location', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                      $vehicleQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('plate_number', 'like', "%{$search}%");
                  });
            });
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
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function today(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservations = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $user->team_id)
            ->today()
            ->orderBy('pickup_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
            'count' => $reservations->count()
        ]);
    }

    /**
     * Get tomorrow's reservations
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function tomorrow(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservations = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $user->team_id)
            ->tomorrow()
            ->orderBy('pickup_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
            'count' => $reservations->count()
        ]);
    }

    /**
     * Get reservations statistics
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $teamId = $user->team_id;

        $stats = [
            'total' => Reservation::where('team_id', $teamId)->count(),
            'today' => Reservation::where('team_id', $teamId)->today()->count(),
            'tomorrow' => Reservation::where('team_id', $teamId)->tomorrow()->count(),
            'pending' => Reservation::where('team_id', $teamId)->pending()->count(),
            'confirmed' => Reservation::where('team_id', $teamId)->confirmed()->count(),
            'completed' => Reservation::where('team_id', $teamId)->completed()->count(),
            'canceled' => Reservation::where('team_id', $teamId)->canceled()->count(),
        ];

        // Revenue statistics
        $revenueStats = [
            'total_revenue' => Reservation::where('team_id', $teamId)
                ->where('status', 'completed')
                ->sum('total_amount'),
            'monthly_revenue' => Reservation::where('team_id', $teamId)
                ->where('status', 'completed')
                ->whereMonth('pickup_date', Carbon::now()->month)
                ->whereYear('pickup_date', Carbon::now()->year)
                ->sum('total_amount'),
            'weekly_revenue' => Reservation::where('team_id', $teamId)
                ->where('status', 'completed')
                ->whereBetween('pickup_date', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ])
                ->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'reservations' => $stats,
                'revenue' => $revenueStats
            ]
        ]);
    }

    /**
     * Get available vehicles for a specific period
     *
     * @param Request $request
     * @return JsonResponse
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

        // Get vehicles that are not reserved during the requested period
        $reservedVehicleIds = Reservation::where('status', '!=', 'canceled')
            ->where(function ($query) use ($request) {
                $query->whereBetween('pickup_date', [$request->pickup_date, $request->return_date])
                      ->orWhereBetween('return_date', [$request->pickup_date, $request->return_date])
                      ->orWhere(function ($q) use ($request) {
                          $q->where('pickup_date', '<=', $request->pickup_date)
                            ->where('return_date', '>=', $request->return_date);
                      });
            })
            ->pluck('vehicle_id');

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $availableVehicles = Vehicle::with(['location'])
            ->where('team_id', $user->team_id)
            ->where('status', 'available')
            ->whereNotIn('id', $reservedVehicleIds)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $availableVehicles,
            'period' => [
                'pickup_date' => $request->pickup_date,
                'return_date' => $request->return_date
            ]
        ]);
    }

    /**
     * Search reservations
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
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

        $searchQuery = (string) $request->input('query');

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401);
        }

        $reservations = Reservation::with(['customer', 'vehicle', 'team'])
            ->where('team_id', $user->team_id)
            ->where(function ($q) use ($searchQuery) {
                $q->where('uid', 'like', "%{$searchQuery}%")
                  ->orWhere('pickup_location', 'like', "%{$searchQuery}%")
                  ->orWhere('notes', 'like', "%{$searchQuery}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($searchQuery) {
                      $customerQuery->where('name', 'like', "%{$searchQuery}%")
                                   ->orWhere('email', 'like', "%{$searchQuery}%")
                                   ->orWhere('phone', 'like', "%{$searchQuery}%");
                  })
                  ->orWhereHas('vehicle', function ($vehicleQuery) use ($searchQuery) {
                      $vehicleQuery->where('name', 'like', "%{$searchQuery}%")
                                  ->orWhere('plate_number', 'like', "%{$searchQuery}%")
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
