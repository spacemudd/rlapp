<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/routes
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:banks,code',
            'account_number' => 'required|string|max:50|unique:banks,account_number',
            'iban' => 'nullable|string|max:34|unique:banks,iban',
            'swift_code' => 'nullable|string|size:8|alpha_num',
            'branch_name' => 'nullable|string|max:255',
            'branch_address' => 'nullable|string|max:500',
            'currency' => 'required|string|size:3|in:AED,USD,EUR,GBP,SAR',
            'opening_balance' => 'nullable|numeric|min:0|max:999999999.99',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Bank name is required.',
            'code.required' => 'Bank code is required.',
            'code.unique' => 'This bank code is already in use.',
            'account_number.required' => 'Account number is required.',
            'account_number.unique' => 'This account number is already in use.',
            'iban.unique' => 'This IBAN is already in use.',
            'swift_code.size' => 'SWIFT code must be exactly 8 characters.',
            'swift_code.alpha_num' => 'SWIFT code must contain only letters and numbers.',
            'currency.required' => 'Currency is required.',
            'currency.size' => 'Currency must be exactly 3 characters.',
            'currency.in' => 'Currency must be one of: AED, USD, EUR, GBP, SAR.',
            'opening_balance.numeric' => 'Opening balance must be a valid number.',
            'opening_balance.min' => 'Opening balance cannot be negative.',
            'opening_balance.max' => 'Opening balance cannot exceed 999,999,999.99.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'bank name',
            'code' => 'bank code',
            'account_number' => 'account number',
            'iban' => 'IBAN',
            'swift_code' => 'SWIFT code',
            'branch_name' => 'branch name',
            'branch_address' => 'branch address',
            'currency' => 'currency',
            'opening_balance' => 'opening balance',
            'is_active' => 'active status',
            'notes' => 'notes',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper($this->code),
            'currency' => strtoupper($this->currency),
            'swift_code' => strtoupper($this->swift_code),
            'iban' => strtoupper(str_replace(' ', '', $this->iban ?? '')),
        ]);
    }
}
