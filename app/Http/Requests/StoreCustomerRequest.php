<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'business_type' => 'required|in:individual,business',
            'business_name' => 'nullable|string|max:255|required_if:business_type,business',
            'driver_name' => 'nullable|string|max:255',
            'trade_license_number' => 'nullable|string|max:255',
            'trade_license_pdf' => 'nullable|file|mimes:pdf|max:10240', // 10MB max
            'visit_visa_pdf' => 'nullable|file|mimes:pdf|max:10240', // 10MB max
            'passport_pdf' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'resident_id_pdf' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:customers',
            'phone' => 'required|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            // Driver's license is always required
            'drivers_license_number' => 'required|string|max:255',
            'drivers_license_expiry' => 'required|date|after:today',
            // Secondary identification type is required
            'secondary_identification_type' => 'required|in:passport,resident_id,visit_visa',
            'country' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string',
        ];

        // Add conditional validation based on secondary identification type
        $secondaryIdentificationType = $this->input('secondary_identification_type');

        switch ($secondaryIdentificationType) {
            case 'resident_id':
                $rules['resident_id_number'] = 'required|string|max:255';
                $rules['resident_id_expiry'] = 'required|date|after:today';
                $rules['resident_id_pdf'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:10240';
                break;
            case 'passport':
                $rules['passport_number'] = 'required|string|max:255';
                $rules['passport_expiry'] = 'required|date|after:today';
                $rules['passport_pdf'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:10240';
                break;
            case 'visit_visa':
                $rules['visit_visa_pdf'] = 'required|file|mimes:pdf|max:10240';
                break;
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'business_type.required' => 'Please select whether this is an individual or business customer.',
            'business_type.in' => 'Invalid customer type selected.',
            'business_name.required_if' => 'Business name is required for business customers.',
            'trade_license_pdf.file' => 'Trade license must be a valid file.',
            'trade_license_pdf.mimes' => 'Trade license must be a PDF file.',
            'trade_license_pdf.max' => 'Trade license file size must not exceed 10MB.',
            'visit_visa_pdf.file' => 'Visit visa must be a valid file.',
            'visit_visa_pdf.mimes' => 'Visit visa must be a PDF file.',
            'visit_visa_pdf.max' => 'Visit visa file size must not exceed 10MB.',
            'secondary_identification_type.required' => 'Please select a secondary identification type.',
            'secondary_identification_type.in' => 'Invalid secondary identification type selected.',
            'drivers_license_number.required' => 'Driver\'s license number is required.',
            'drivers_license_expiry.required' => 'Driver\'s license expiry date is required.',
            'drivers_license_expiry.after' => 'Driver\'s license must not be expired.',
            'passport_number.required' => 'Passport number is required when using passport identification.',
            'passport_expiry.required' => 'Passport expiry date is required when using passport identification.',
            'passport_expiry.after' => 'Passport must not be expired.',
            'resident_id_number.required' => 'Resident ID number is required when using resident ID identification.',
            'resident_id_expiry.required' => 'Resident ID expiry date is required when using resident ID identification.',
            'resident_id_expiry.after' => 'Resident ID must not be expired.',
            'nationality.required' => 'Nationality is required.',
            'passport_pdf.required' => 'Passport document is required when using passport identification.',
            'passport_pdf.mimes' => 'Passport document must be a PDF, JPG, or PNG.',
            'passport_pdf.max' => 'Passport document must not exceed 10MB.',
            'resident_id_pdf.required' => 'Resident ID document is required when using resident ID identification.',
            'resident_id_pdf.mimes' => 'Resident ID document must be a PDF, JPG, or PNG.',
            'resident_id_pdf.max' => 'Resident ID document must not exceed 10MB.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clear identification fields that aren't relevant to the selected secondary type
        $secondaryIdentificationType = $this->input('secondary_identification_type');

        $fieldsToNull = [];

        // Driver's license is always required, so we don't null it

        if ($secondaryIdentificationType !== 'resident_id') {
            $fieldsToNull = array_merge($fieldsToNull, ['resident_id_number', 'resident_id_expiry', 'resident_id_pdf']);
        }

        if ($secondaryIdentificationType !== 'passport') {
            $fieldsToNull = array_merge($fieldsToNull, ['passport_number', 'passport_expiry', 'passport_pdf']);
        }

        if ($secondaryIdentificationType !== 'visit_visa') {
            $fieldsToNull = array_merge($fieldsToNull, ['visit_visa_pdf']);
        }

        // Set irrelevant fields to null
        foreach ($fieldsToNull as $field) {
            if ($this->has($field)) {
                $this->merge([$field => null]);
            }
        }
    }
}
