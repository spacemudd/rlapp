<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class TeamManagementController extends Controller
{
    /**
     * Display team management page
     */
    public function index()
    {
        $user = Auth::user();
        
        // Check if user can manage team settings
        if (!$user->can('manage team settings')) {
            abort(403, 'You do not have permission to manage team settings');
        }
        
        $team = $user->team;

        if (!$team) {
            abort(404, 'Team not found');
        }

        // Set permissions team context
        setPermissionsTeamId($team->id);

        // Get team members with their roles
        $members = User::where('team_id', $team->id)
            ->with('roles')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'roles' => $member->roles->pluck('name'),
                    'created_at' => $member->created_at,
                ];
            });

        // Get pending invitations
        $pendingInvitations = UserInvitation::where('team_id', $team->id)
            ->pending()
            ->with('invitedBy')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'invited_by' => $invitation->invitedBy->name,
                    'expires_at' => $invitation->expires_at,
                    'created_at' => $invitation->created_at,
                    'invitation_url' => route('invitation.show', $invitation->token),
                ];
            });

        // Get available roles for this team
        $availableRoles = Role::where('team_id', $team->id)
            ->pluck('name', 'name')
            ->toArray();

        return Inertia::render('TeamManagement', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
            ],
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'availableRoles' => $availableRoles,
            'canInviteUsers' => $user->can('create users'),
        ]);
    }

    /**
     * Update user role
     */
    public function updateUserRole(Request $request, User $user)
    {
        $currentUser = Auth::user();
        
        // Check if user can manage team settings
        if (!$currentUser->can('manage team settings')) {
            abort(403, 'You do not have permission to manage team settings');
        }
        
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);
        
        // Check if user belongs to same team
        if ($user->team_id !== $currentUser->team_id) {
            abort(403, 'Cannot manage users from different teams');
        }

        // Don't allow changing your own role
        if ($user->id === $currentUser->id) {
            abort(403, 'You cannot change your own role');
        }

        // Set permissions team context
        setPermissionsTeamId($currentUser->team_id);

        // Remove all current roles and assign new role
        $user->syncRoles([$request->role]);

        return redirect()->back()->with('success', 'User role updated successfully');
    }

    /**
     * Send invitation
     */
    public function sendInvitation(Request $request)
    {
        $currentUser = Auth::user();
        
        // Check if user can create users (invite members)
        if (!$currentUser->can('create users')) {
            abort(403, 'You do not have permission to invite users');
        }
        
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|exists:roles,name',
        ]);
        $team = $currentUser->team;

        // Check if there's already a pending invitation for this email
        $existingInvitation = UserInvitation::where('team_id', $team->id)
            ->where('email', $request->email)
            ->pending()
            ->first();

        if ($existingInvitation) {
            return redirect()->back()->withErrors(['email' => 'There is already a pending invitation for this email']);
        }

        // Create invitation
        $invitation = UserInvitation::create([
            'team_id' => $team->id,
            'invited_by' => $currentUser->id,
            'email' => $request->email,
            'role' => $request->role,
            'token' => UserInvitation::generateToken(),
            'expires_at' => now()->addDays(7), // Expire in 7 days
        ]);

        // Send invitation email (we'll implement this)
        $this->sendInvitationEmail($invitation);

        // Return the invitation URL for copying
        $invitationUrl = route('invitation.show', $invitation->token);

        return redirect()->back()->with([
            'success' => 'Invitation sent successfully',
            'invitationUrl' => $invitationUrl,
            'invitationEmail' => $invitation->email,
        ]);
    }

    /**
     * Cancel invitation
     */
    public function cancelInvitation(UserInvitation $invitation)
    {
        $currentUser = Auth::user();
        
        // Check if user can manage team settings
        if (!$currentUser->can('manage team settings')) {
            abort(403, 'You do not have permission to manage team settings');
        }

        // Check if invitation belongs to current user's team
        if ($invitation->team_id !== $currentUser->team_id) {
            abort(403, 'Cannot cancel invitations from different teams');
        }

        $invitation->delete();

        return redirect()->back()->with('success', 'Invitation cancelled successfully');
    }

    /**
     * Remove user from team
     */
    public function removeUser(User $user)
    {
        $currentUser = Auth::user();
        
        // Check if user can manage team settings
        if (!$currentUser->can('manage team settings')) {
            abort(403, 'You do not have permission to manage team settings');
        }

        // Check if user belongs to same team
        if ($user->team_id !== $currentUser->team_id) {
            abort(403, 'Cannot remove users from different teams');
        }

        // Don't allow removing yourself
        if ($user->id === $currentUser->id) {
            abort(403, 'You cannot remove yourself from the team');
        }

        // Set permissions team context and remove roles
        setPermissionsTeamId($currentUser->team_id);
        $user->syncRoles([]);

        // Remove user from team (set team_id to null or delete - depending on your business logic)
        $user->update(['team_id' => null]);

        return redirect()->back()->with('success', 'User removed from team successfully');
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail(UserInvitation $invitation)
    {
        // For now, we'll just log it. You can implement actual email sending later
        \Log::info('Invitation email should be sent', [
            'email' => $invitation->email,
            'team' => $invitation->team->name,
            'role' => $invitation->role,
            'token' => $invitation->token,
            'invitation_url' => route('invitation.accept', $invitation->token),
        ]);

        // TODO: Implement actual email sending
        // Mail::to($invitation->email)->send(new TeamInvitationMail($invitation));
    }
}
