<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'entity_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the users that belong to the team.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the IFRS entity that the team belongs to.
     */
    public function entity()
    {
        return $this->belongsTo(\IFRS\Models\Entity::class);
    }

    /**
     * Get the customers that belong to the team.
     */
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Set this team as the active team for permissions
     */
    public function setAsActiveTeam()
    {
        setPermissionsTeamId($this->id);
        return $this;
    }

    /**
     * Create a role for this team
     */
    public function createRole($name, $permissions = [])
    {
        // Temporarily set this team as active
        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId($this->id);
        
        // Create role for this team
        $role = \Spatie\Permission\Models\Role::create([
            'name' => $name,
            'team_id' => $this->id
        ]);
        
        // Assign permissions if provided
        if (!empty($permissions)) {
            $role->givePermissionTo($permissions);
        }
        
        // Restore previous team ID
        if ($currentTeamId) {
            setPermissionsTeamId($currentTeamId);
        }
        
        return $role;
    }
} 