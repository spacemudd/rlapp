<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

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
            // الحقول المسموحة فقط (الموجودة في قاعدة البيانات)
            $allowedFields = [
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

            // استخراج البيانات المسموحة فقط من الطلب
            $requestData = $request->only($allowedFields);

            // التحقق من الصحة
            $validator = Validator::make($requestData, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'nullable|email|unique:customers,email',
                'phone' => 'required|string|max:20',
                'date_of_birth' => 'nullable|date',
                'drivers_license_number' => 'nullable|string|max:50',
                'drivers_license_expiry' => 'nullable|date|after:today',
                'country' => 'nullable|string|max:100',
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:20',
                'status' => 'nullable|in:active,inactive',
                'notes' => 'nullable|string',
                'secondary_identification_type' => 'nullable|in:passport,resident_id',
                'passport_number' => 'nullable|string|max:50',
                'passport_expiry' => 'nullable|date|after:today',
                'resident_id_number' => 'nullable|string|max:50',
                'resident_id_expiry' => 'nullable|date|after:today',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'nationality' => 'nullable|string|max:100',
                'team_id' => 'nullable|exists:teams,id',
                // VAT validation rules
                'vat_number' => 'nullable|string|max:20',
                'vat_registered' => 'nullable|boolean',
                'vat_registration_date' => 'nullable|date',
                'vat_registration_country' => 'nullable|string|max:3',
                'customer_type' => 'nullable|in:local,export,gcc,other',
                'reverse_charge_applicable' => 'nullable|boolean',
                'tax_classification' => 'nullable|string|max:255',
                'vat_number_validated' => 'nullable|boolean',
                'vat_notes' => 'nullable|string',
                // IFRS validation rules
                'ifrs_receivable_account_id' => 'nullable|exists:ifrs_accounts,id',
                'credit_limit' => 'nullable|numeric|min:0',
                'payment_terms' => 'nullable|in:cash,15_days,30_days,60_days,90_days'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 422);
            }

            // تعيين القيم الافتراضية إذا لم يتم توفيرها
            if (!isset($requestData['team_id']) || empty($requestData['team_id'])) {
                // الحصول على أول فريق موجود
                $firstTeam = Team::first();
                if ($firstTeam) {
                    $requestData['team_id'] = $firstTeam->id;
                } else {
                    // إذا لم توجد فرق، اتركه null (حسب migration هو nullable)
                    $requestData['team_id'] = null;
                }
            }

            if (!isset($requestData['country'])) {
                $requestData['country'] = 'United Arab Emirates';
            }

            if (!isset($requestData['status'])) {
                $requestData['status'] = 'active';
            }

            if (!isset($requestData['vat_registered'])) {
                $requestData['vat_registered'] = false;
            }

            if (!isset($requestData['customer_type'])) {
                $requestData['customer_type'] = 'local';
            }

            if (!isset($requestData['payment_terms'])) {
                $requestData['payment_terms'] = 'cash';
            }

            // إضافة قيم افتراضية لرخصة القيادة إذا لم يتم توفيرها
            if (!isset($requestData['drivers_license_number']) || empty($requestData['drivers_license_number'])) {
                $requestData['drivers_license_number'] = 'TBD-' . uniqid(); // To Be Determined
            }

            if (!isset($requestData['drivers_license_expiry']) || empty($requestData['drivers_license_expiry'])) {
                $requestData['drivers_license_expiry'] = now()->addYear(); // سنة من الآن
            }

            // إضافة قيم افتراضية للعنوان والمدينة إذا لم يتم توفيرها
            if (!isset($requestData['address']) || empty($requestData['address'])) {
                $requestData['address'] = 'To be updated'; // سيتم التحديث
            }

            if (!isset($requestData['city']) || empty($requestData['city'])) {
                $requestData['city'] = 'Dubai'; // افتراضي: دبي
            }

            // إنشاء العميل
            $customer = Customer::create($requestData);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العميل بنجاح',
                'customer' => $customer,
                'ignored_fields' => array_diff(array_keys($request->all()), $allowedFields)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء العميل',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
