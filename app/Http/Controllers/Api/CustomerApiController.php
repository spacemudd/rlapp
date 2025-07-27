<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class CustomerApiController extends Controller
{
    /**
     * إنشاء عميل جديد
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // الحصول على أعمدة جدول العملاء الفعلية
            $tableColumns = Schema::getColumnListing('customers');

            // الحقول المحتملة (سيتم فلترتها حسب ما هو موجود في قاعدة البيانات)
            $possibleFields = [
                'first_name',
                'last_name',
                'email',
                'phone',
                'date_of_birth',
                'drivers_license_number',
                'drivers_license_expiry',
                'country',
                'emergency_contact_name',
                'emergency_contact_phone',
                'status',
                'notes',
                'secondary_identification_type',
                'passport_number',
                'passport_expiry',
                'resident_id_number',
                'resident_id_expiry',
                'address',
                'city',
                'nationality',
                'team_id',
                // حقول VAT الجديدة
                'vat_number',
                'vat_registered',
                'vat_registration_date',
                'vat_registration_country',
                'customer_type',
                'reverse_charge_applicable',
                'tax_classification',
                'vat_number_validated',
                'vat_number_validated_at',
                'vat_validation_response',
                'vat_notes',
                // حقول IFRS
                'ifrs_receivable_account_id',
                'credit_limit',
                'payment_terms'
            ];

            // فلترة الحقول المسموحة بناءً على ما هو موجود فعلياً في قاعدة البيانات
            $allowedFields = array_intersect($possibleFields, $tableColumns);

            // استخراج البيانات المسموحة فقط من الطلب
            $requestData = $request->only($allowedFields);

            // إنشاء قواعد التحقق الديناميكية بناءً على الحقول الموجودة
            $validationRules = [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
            ];

            // إضافة قواعد للحقول إذا كانت موجودة
            if (in_array('email', $allowedFields)) {
                $validationRules['email'] = 'nullable|email|unique:customers,email';
            }
            if (in_array('date_of_birth', $allowedFields)) {
                $validationRules['date_of_birth'] = 'nullable|date';
            }
            if (in_array('drivers_license_number', $allowedFields)) {
                $validationRules['drivers_license_number'] = 'nullable|string|max:50';
            }
            if (in_array('drivers_license_expiry', $allowedFields)) {
                $validationRules['drivers_license_expiry'] = 'nullable|date|after:today';
            }
            if (in_array('address', $allowedFields)) {
                $validationRules['address'] = 'nullable|string|max:500';
            }
            if (in_array('city', $allowedFields)) {
                $validationRules['city'] = 'nullable|string|max:100';
            }
            if (in_array('nationality', $allowedFields)) {
                $validationRules['nationality'] = 'nullable|string|max:100';
            }
            if (in_array('team_id', $allowedFields)) {
                $validationRules['team_id'] = 'nullable|exists:teams,id';
            }

            // التحقق من الصحة
            $validator = Validator::make($requestData, $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 422);
            }

            // تعيين القيم الافتراضية إذا لم يتم توفيرها
            if (in_array('team_id', $allowedFields) && (!isset($requestData['team_id']) || empty($requestData['team_id']))) {
                // الحصول على أول فريق موجود
                $firstTeam = Team::first();
                if ($firstTeam) {
                    $requestData['team_id'] = $firstTeam->id;
                } else {
                    // إذا لم توجد فرق، اتركه null (حسب migration هو nullable)
                    $requestData['team_id'] = null;
                }
            }

            if (in_array('country', $allowedFields) && !isset($requestData['country'])) {
                $requestData['country'] = 'United Arab Emirates';
            }

            if (in_array('status', $allowedFields) && !isset($requestData['status'])) {
                $requestData['status'] = 'active';
            }

            if (in_array('vat_registered', $allowedFields) && !isset($requestData['vat_registered'])) {
                $requestData['vat_registered'] = false;
            }

            if (in_array('customer_type', $allowedFields) && !isset($requestData['customer_type'])) {
                $requestData['customer_type'] = 'local';
            }

            if (in_array('payment_terms', $allowedFields) && !isset($requestData['payment_terms'])) {
                $requestData['payment_terms'] = 'cash';
            }

            // إضافة قيم افتراضية لرخصة القيادة إذا لم يتم توفيرها
            if (in_array('drivers_license_number', $allowedFields) && (!isset($requestData['drivers_license_number']) || empty($requestData['drivers_license_number']))) {
                $requestData['drivers_license_number'] = 'TBD-' . uniqid(); // To Be Determined
            }

            if (in_array('drivers_license_expiry', $allowedFields) && (!isset($requestData['drivers_license_expiry']) || empty($requestData['drivers_license_expiry']))) {
                $requestData['drivers_license_expiry'] = now()->addYear(); // سنة من الآن
            }

            // إضافة قيم افتراضية للعنوان والمدينة إذا كانت موجودة في قاعدة البيانات
            if (in_array('address', $allowedFields) && (!isset($requestData['address']) || empty($requestData['address']))) {
                $requestData['address'] = 'To be updated'; // سيتم التحديث
            }

            if (in_array('city', $allowedFields) && (!isset($requestData['city']) || empty($requestData['city']))) {
                $requestData['city'] = 'Dubai'; // افتراضي: دبي
            }

            // إنشاء العميل
            $customer = Customer::create($requestData);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العميل بنجاح',
                'customer' => $customer,
                'ignored_fields' => array_diff(array_keys($request->all()), $allowedFields),
                'database_columns' => $tableColumns // للتشخيص
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
                'input_data' => $request->all() // للتشخيص
            ], 500);
        }
    }

    /**
     * Get all customers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get team_id from authenticated user or from request
            $teamId = Auth::check() ? Auth::user()->team_id : $request->header('X-TEAM-ID', 1);

            $search = $request->query('search');
            $status = $request->query('status');
            $perPage = min($request->query('per_page', 15), 100);
            $page = $request->query('page', 1);

            $query = Customer::where('team_id', $teamId);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            $customers = $query->orderBy('first_name')->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $customers->items(),
                'pagination' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem(),
                ],
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific customer
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            // Get team_id from authenticated user or from request
            $teamId = Auth::check() ? Auth::user()->team_id : $request->header('X-TEAM-ID', 1);

            $customer = Customer::where('team_id', $teamId)->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            // Get team_id from authenticated user or from request
            $teamId = Auth::check() ? Auth::user()->team_id : $request->header('X-TEAM-ID', 1);

            $customer = Customer::where('team_id', $teamId)->findOrFail($id);

            // الحصول على أعمدة جدول العملاء الفعلية
            $tableColumns = Schema::getColumnListing('customers');

            // الحقول المحتملة (سيتم فلترتها حسب ما هو موجود في قاعدة البيانات)
            $possibleFields = [
                'first_name',
                'last_name',
                'email',
                'phone',
                'date_of_birth',
                'drivers_license_number',
                'drivers_license_expiry',
                'country',
                'emergency_contact_name',
                'emergency_contact_phone',
                'status',
                'notes',
                'secondary_identification_type',
                'passport_number',
                'passport_expiry',
                'resident_id_number',
                'resident_id_expiry',
                'address',
                'city',
                'nationality',
                'vat_number',
                'vat_registered',
                'vat_registration_date',
                'vat_registration_country',
                'customer_type',
                'reverse_charge_applicable',
                'tax_classification',
                'vat_number_validated',
                'vat_number_validated_at',
                'vat_validation_response',
                'vat_notes',
                'ifrs_receivable_account_id',
                'credit_limit',
                'payment_terms'
            ];

            // فلترة الحقول المسموحة بناءً على ما هو موجود فعلياً في قاعدة البيانات
            $allowedFields = array_intersect($possibleFields, $tableColumns);

            // استخراج البيانات المسموحة فقط من الطلب
            $requestData = $request->only($allowedFields);

            // إنشاء قواعد التحقق الديناميكية بناءً على الحقول الموجودة
            $validationRules = [
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'phone' => 'sometimes|required|string|max:20',
            ];

            // إضافة قواعد للحقول إذا كانت موجودة
            if (in_array('email', $allowedFields)) {
                $validationRules['email'] = 'nullable|email|unique:customers,email,' . $customer->id;
            }
            if (in_array('date_of_birth', $allowedFields)) {
                $validationRules['date_of_birth'] = 'nullable|date';
            }
            if (in_array('drivers_license_number', $allowedFields)) {
                $validationRules['drivers_license_number'] = 'nullable|string|max:50';
            }
            if (in_array('drivers_license_expiry', $allowedFields)) {
                $validationRules['drivers_license_expiry'] = 'nullable|date|after:today';
            }
            if (in_array('address', $allowedFields)) {
                $validationRules['address'] = 'nullable|string|max:500';
            }
            if (in_array('city', $allowedFields)) {
                $validationRules['city'] = 'nullable|string|max:100';
            }
            if (in_array('nationality', $allowedFields)) {
                $validationRules['nationality'] = 'nullable|string|max:100';
            }
            if (in_array('country', $allowedFields)) {
                $validationRules['country'] = 'nullable|string|max:100';
            }
            if (in_array('emergency_contact_name', $allowedFields)) {
                $validationRules['emergency_contact_name'] = 'nullable|string|max:255';
            }
            if (in_array('emergency_contact_phone', $allowedFields)) {
                $validationRules['emergency_contact_phone'] = 'nullable|string|max:20';
            }
            if (in_array('status', $allowedFields)) {
                $validationRules['status'] = 'nullable|in:active,inactive';
            }
            if (in_array('notes', $allowedFields)) {
                $validationRules['notes'] = 'nullable|string';
            }

            $validator = Validator::make($requestData, $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $customer->update($requestData);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete customer
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            // Get team_id from authenticated user or from request
            $teamId = Auth::check() ? Auth::user()->team_id : $request->header('X-TEAM-ID', 1);

            $customer = Customer::where('team_id', $teamId)->findOrFail($id);
            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search customers
     */
    public function search(Request $request): JsonResponse
    {
        try {
            // Get team_id from authenticated user or from request
            $teamId = Auth::check() ? Auth::user()->team_id : $request->header('X-TEAM-ID', 1);

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

            $customers = Customer::where('team_id', $teamId)
                ->where(function ($q) use ($searchQuery) {
                    $q->where('first_name', 'like', "%{$searchQuery}%")
                      ->orWhere('last_name', 'like', "%{$searchQuery}%")
                      ->orWhere('email', 'like', "%{$searchQuery}%")
                      ->orWhere('phone', 'like', "%{$searchQuery}%");
                })
                ->orderBy('first_name')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $customers,
                'query' => $searchQuery,
                'count' => $customers->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer reservations
     */
    public function reservations(Request $request, string $id): JsonResponse
    {
        try {
            // Get team_id from authenticated user or from request
            $teamId = Auth::check() ? Auth::user()->team_id : $request->header('X-TEAM-ID', 1);

            $customer = Customer::where('team_id', $teamId)->findOrFail($id);

            $reservations = $customer->reservations()
                ->with(['vehicle', 'team'])
                ->orderBy('pickup_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reservations,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                'count' => $reservations->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching customer reservations: ' . $e->getMessage()
            ], 500);
        }
    }
}
