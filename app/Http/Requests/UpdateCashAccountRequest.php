<?php

namespace App\Http\Requests;

use App\Models\CashAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashAccountRequest extends FormRequest
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
        $cashAccountId = $this->route('cashAccount')->id;
        $accountTypes = array_keys(CashAccount::getAccountTypes());

        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('cash_accounts', 'code')->ignore($cashAccountId)
            ],
            'type' => 'required|string|in:' . implode(',', $accountTypes),
            'location' => 'nullable|string|max:255',
            'currency' => 'required|string|size:3|in:AED,USD,EUR,GBP,SAR',
            'limit_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'is_active' => 'boolean',
            'responsible_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Cash account name is required.',
            'code.required' => 'Cash account code is required.',
            'code.unique' => 'This cash account code is already in use.',
            'type.required' => 'Cash account type is required.',
            'type.in' => 'Please select a valid cash account type.',
            'currency.required' => 'Currency is required.',
            'currency.size' => 'Currency must be exactly 3 characters.',
            'currency.in' => 'Currency must be one of: AED, USD, EUR, GBP, SAR.',
            'limit_amount.numeric' => 'Limit amount must be a valid number.',
            'limit_amount.min' => 'Limit amount cannot be negative.',
            'limit_amount.max' => 'Limit amount cannot exceed 999,999,999.99.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'cash account name',
            'code' => 'cash account code',
            'type' => 'cash account type',
            'location' => 'location',
            'currency' => 'currency',
            'limit_amount' => 'limit amount',
            'is_active' => 'active status',
            'responsible_person' => 'responsible person',
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
            'type' => strtolower($this->type),
        ]);
    }
}
