<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

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
}
