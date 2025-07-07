<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'showDevLogin' => app()->environment(['local', 'testing']) && in_array($request->getHost(), ['localhost', '127.0.0.1']),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Dev login - only works on localhost in local environment
     */
    public function devLogin(Request $request): RedirectResponse
    {
        $allowedHosts = ['localhost', '127.0.0.1'];
        
        // In testing environment, also allow the test host
        if (app()->environment('testing')) {
            $allowedHosts[] = 'rlapp.test';
        }

        // Only allow in local/testing environment and allowed hosts
        if (!app()->environment(['local', 'testing']) || !in_array($request->getHost(), $allowedHosts)) {
            abort(404);
        }

        // Find the first user (assuming it's an admin)
        $user = User::orderBy('created_at')->first();
        
        if (!$user) {
            return redirect()->route('login')->with('status', 'No users found for dev login.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
