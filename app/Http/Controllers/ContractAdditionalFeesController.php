<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractAdditionalFee;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class ContractAdditionalFeesController extends Controller
{
    /**
     * Store multiple fee items for a contract.
     */
    public function store(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'fees' => 'required|array|min:1',
            'fees.*.fee_type' => 'required|string|max:255',
            'fees.*.description' => 'nullable|string',
            'fees.*.quantity' => 'required|numeric|min:0.01',
            'fees.*.unit_price' => 'required|numeric|min:0',
            'fees.*.discount' => 'nullable|numeric|min:0',
            'fees.*.is_vat_exempt' => 'boolean',
            'fees.*.vat_account_id' => 'nullable|exists:ifrs_accounts,id',
        ]);

        // Validate that fee types exist in system settings
        $validFeeTypes = collect(SystemSetting::getFeeTypes())->pluck('key')->toArray();
        
        foreach ($validated['fees'] as $fee) {
            if (!in_array($fee['fee_type'], $validFeeTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid fee type: {$fee['fee_type']}",
                ], 422);
            }
        }

        $createdFees = [];

        foreach ($validated['fees'] as $feeData) {
            $fee = $contract->additionalFees()->create([
                'fee_type' => $feeData['fee_type'],
                'description' => $feeData['description'] ?? null,
                'quantity' => $feeData['quantity'],
                'unit_price' => $feeData['unit_price'],
                'discount' => $feeData['discount'] ?? 0,
                'is_vat_exempt' => $feeData['is_vat_exempt'] ?? false,
                'vat_account_id' => $feeData['vat_account_id'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $createdFees[] = $fee;
        }

        return response()->json([
            'success' => true,
            'message' => 'Additional fees added successfully.',
            'fees' => $createdFees,
        ]);
    }

    /**
     * Delete a fee item.
     */
    public function destroy(ContractAdditionalFee $fee)
    {
        $fee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fee deleted successfully.',
        ]);
    }
}

