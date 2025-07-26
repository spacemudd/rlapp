<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthApiController extends Controller
{
    /**
     * Handle API login and return a token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();
        // Generate a plain token (for demo, use createToken if using Sanctum)
        if (method_exists($user, 'createToken')) {
            $token = $user->createToken('api-token')->plainTextToken;
        } else {
            // fallback: just return user id for demo (not secure)
            $token = base64_encode($user->id . '|' . now());
        }

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
}
