<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
        $customer = $this->route('customer');
        
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:customers,email,' . $customer->id,
            'phone' => 'required|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            // Driver's license is always required
            'drivers_license_number' => 'required|string|max:255',
            'drivers_license_expiry' => 'required|date|after:today',
            // Secondary identification type is required
            'secondary_identification_type' => 'required|in:passport,resident_id',
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
            case 'passport':
                $rules['passport_number'] = 'required|string|max:255';
                $rules['passport_expiry'] = 'required|date|after:today';
                break;
            case 'resident_id':
                $rules['resident_id_number'] = 'required|string|max:255';
                $rules['resident_id_expiry'] = 'required|date|after:today';
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
        
        if ($secondaryIdentificationType !== 'passport') {
            $fieldsToNull = array_merge($fieldsToNull, ['passport_number', 'passport_expiry']);
        }
        
        if ($secondaryIdentificationType !== 'resident_id') {
            $fieldsToNull = array_merge($fieldsToNull, ['resident_id_number', 'resident_id_expiry']);
        }
        
        // Set irrelevant fields to null
        foreach ($fieldsToNull as $field) {
            if ($this->has($field)) {
                $this->merge([$field => null]);
            }
        }
    }
}
