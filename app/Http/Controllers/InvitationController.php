<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class InvitationController extends Controller
{
    /**
     * Show invitation acceptance form
     */
    public function show(string $token)
    {
        $invitation = UserInvitation::where('token', $token)
            ->with(['team', 'invitedBy'])
            ->first();

        if (!$invitation) {
            return Inertia::render('auth/InvitationNotFound');
        }

        if ($invitation->isExpired()) {
            return Inertia::render('auth/InvitationExpired', [
                'invitation' => [
                    'email' => $invitation->email,
                    'team_name' => $invitation->team->name,
                    'expires_at' => $invitation->expires_at,
                ]
            ]);
        }

        if ($invitation->isAccepted()) {
            return Inertia::render('auth/InvitationAlreadyAccepted', [
                'invitation' => [
                    'email' => $invitation->email,
                    'team_name' => $invitation->team->name,
                    'accepted_at' => $invitation->accepted_at,
                ]
            ]);
        }

        // Check if user already exists with this email
        $existingUser = User::where('email', $invitation->email)->first();
        if ($existingUser) {
            return Inertia::render('auth/InvitationUserExists', [
                'invitation' => [
                    'email' => $invitation->email,
                    'team_name' => $invitation->team->name,
                    'role' => $invitation->role,
                ]
            ]);
        }

        return Inertia::render('auth/AcceptInvitation', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'team_name' => $invitation->team->name,
                'invited_by' => $invitation->invitedBy->name,
                'expires_at' => $invitation->expires_at,
            ]
        ]);
    }

    /**
     * Accept invitation and create user account
     */
    public function accept(Request $request, string $token)
    {
        $invitation = UserInvitation::where('token', $token)->first();

        if (!$invitation) {
            return redirect()->route('login')->withErrors(['error' => 'Invalid invitation token']);
        }

        if ($invitation->isExpired()) {
            return redirect()->route('login')->withErrors(['error' => 'This invitation has expired']);
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')->withErrors(['error' => 'This invitation has already been accepted']);
        }

        // Check if user already exists
        $existingUser = User::where('email', $invitation->email)->first();
        if ($existingUser) {
            return redirect()->route('login')->withErrors(['error' => 'An account with this email already exists']);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'team_id' => $invitation->team_id,
            'email_verified_at' => now(), // Auto-verify email since they were invited
        ]);

        // Set team context and assign role
        setPermissionsTeamId($invitation->team_id);
        $user->assignRole($invitation->role);

        // Mark invitation as accepted
        $invitation->accept($user);

        // Log the user in
        auth()->login($user);

        return redirect()->route('dashboard')->with('success', 'Welcome to ' . $invitation->team->name . '! Your account has been created and you have been assigned the ' . $invitation->role . ' role.');
    }

    /**
     * Decline invitation
     */
    public function decline(string $token)
    {
        $invitation = UserInvitation::where('token', $token)->first();

        if (!$invitation) {
            return redirect()->route('login')->withErrors(['error' => 'Invalid invitation token']);
        }

        // Delete the invitation
        $invitation->delete();

        return Inertia::render('auth/InvitationDeclined', [
            'team_name' => $invitation->team->name,
        ]);
    }
}
