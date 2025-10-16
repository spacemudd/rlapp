<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemSettingsController extends Controller
{
    /**
     * Display system settings page with fee types.
     */
    public function index()
    {
        $feeTypes = SystemSetting::getFeeTypes();

        return Inertia::render('settings/FeeTypes', [
            'feeTypes' => $feeTypes,
        ]);
    }

    /**
     * Update fee types configuration.
     */
    public function updateFeeTypes(Request $request)
    {
        $validated = $request->validate([
            'fee_types' => 'required|array|min:1',
            'fee_types.*.key' => 'required|string|max:255',
            'fee_types.*.en' => 'required|string|max:255',
            'fee_types.*.ar' => 'required|string|max:255',
        ], [
            'fee_types.required' => 'At least one fee type is required.',
            'fee_types.*.key.required' => 'Fee type key is required.',
            'fee_types.*.en.required' => 'English name is required for all fee types.',
            'fee_types.*.ar.required' => 'Arabic name is required for all fee types.',
        ]);

        // Ensure unique keys
        $keys = collect($validated['fee_types'])->pluck('key')->toArray();
        if (count($keys) !== count(array_unique($keys))) {
            return redirect()->back()->withErrors(['fee_types' => 'Fee type keys must be unique.']);
        }

        SystemSetting::setFeeTypes($validated['fee_types']);

        return redirect()->back()->with('success', 'Fee types updated successfully.');
    }
}

