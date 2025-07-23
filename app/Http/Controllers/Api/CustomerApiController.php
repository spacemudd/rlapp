<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CustomerApiController extends Controller
{
    /**
     * Store a new customer (test endpoint, no auth)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Allowed fields for customers table
        $allowedFields = [
            'name',
            'email',
            'phone',
            'address',
            'identification_number',
            'identification_type',
            'notes',
            'team_id',
        ];

        // Minimal validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Filter only allowed fields
        $customerData = array_intersect_key($request->all(), array_flip($allowedFields));

        // Use first team if not provided
        if (empty($customerData['team_id'])) {
            $firstTeam = \App\Models\Team::first();
            if ($firstTeam) {
                $customerData['team_id'] = $firstTeam->id;
            }
        }

        try {
            $customer = Customer::create($customerData);
            $extraFields = array_diff_key($request->all(), array_flip($allowedFields));
            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer,
                'saved_fields' => $customerData,
                'ignored_fields' => $extraFields
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
                'input_data' => $request->all()
            ], 500);
        }
    }
}
