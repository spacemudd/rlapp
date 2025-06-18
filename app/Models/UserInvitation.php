<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInvitation extends Model
{
    protected $fillable = [
        'team_id',
        'invited_by',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Generate a unique invitation token
     */
    public static function generateToken(): string
    {
        return Str::random(32);
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if invitation is accepted
     */
    public function isAccepted(): bool
    {
        return !is_null($this->accepted_at);
    }

    /**
     * Mark invitation as accepted
     */
    public function accept(User $user): void
    {
        $this->update([
            'accepted_at' => now(),
            'user_id' => $user->id,
        ]);
    }

    /**
     * Get the team that owns the invitation
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who sent the invitation
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the user who accepted the invitation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending invitations
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * Scope for expired invitations
     */
    public function scopeExpired($query)
    {
        return $query->whereNull('accepted_at')->where('expires_at', '<=', now());
    }
}
