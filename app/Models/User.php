<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'team_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the team that the user belongs to.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Switch to a different team and set permissions team ID
     */
    public function switchTeam(Team $team)
    {
        $this->update(['team_id' => $team->id]);
        
        // Set the permissions team ID
        setPermissionsTeamId($team->id);
        
        // Unset cached relations to reload with new team context
        $this->unsetRelation('roles')->unsetRelation('permissions');
        
        return $this;
    }

    /**
     * Set permissions team ID for current user's team
     */
    public function setPermissionsForCurrentTeam()
    {
        if ($this->team_id) {
            setPermissionsTeamId($this->team_id);
        }
    }
}
