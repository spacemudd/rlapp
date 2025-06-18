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
            'identification_type' => 'required|in:drivers_license,passport,resident_id',
            'country' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string',
        ];

        // Add conditional validation based on identification type
        $identificationType = $this->input('identification_type');
        
        switch ($identificationType) {
            case 'drivers_license':
                $rules['drivers_license_number'] = 'required|string|max:255';
                $rules['drivers_license_expiry'] = 'required|date|after:today';
                break;
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
            'identification_type.required' => 'Please select an identification type.',
            'identification_type.in' => 'Invalid identification type selected.',
            'drivers_license_number.required' => 'Driver\'s license number is required when using driver\'s license identification.',
            'drivers_license_expiry.required' => 'Driver\'s license expiry date is required when using driver\'s license identification.',
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
        // Clear identification fields that aren't relevant to the selected type
        $identificationType = $this->input('identification_type');
        
        $fieldsToNull = [];
        
        if ($identificationType !== 'drivers_license') {
            $fieldsToNull = array_merge($fieldsToNull, ['drivers_license_number', 'drivers_license_expiry']);
        }
        
        if ($identificationType !== 'passport') {
            $fieldsToNull = array_merge($fieldsToNull, ['passport_number', 'passport_expiry']);
        }
        
        if ($identificationType !== 'resident_id') {
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
