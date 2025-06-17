<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TeamRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions first (these are global)
        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Customer management
            'view customers',
            'create customers',
            'edit customers',
            'delete customers',
            
            // Invoice management
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'approve invoices',
            'send invoices',
            
            // Vehicle management
            'view vehicles',
            'create vehicles',
            'edit vehicles',
            'delete vehicles',
            
            // Financial operations
            'view financial reports',
            'manage payments',
            'view payment history',
            'export financial data',
            
            // Team management
            'manage team settings',
            'view team analytics',
            
            // System administration
            'manage system settings',
            'view system logs',
            'backup system',
        ];

        // Create permissions (global - not team specific)
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $this->command->info("✅ Created " . count($permissions) . " permissions");

        // Get all teams and create roles for each
        $teams = Team::all();
        
        foreach ($teams as $team) {
            $this->createRolesForTeam($team);
        }
    }

    private function createRolesForTeam(Team $team): void
    {
        // Set this team as active for permissions
        setPermissionsTeamId($team->id);

        // Create Admin role with all permissions
        $adminRole = Role::create([
            'name' => 'Admin',
            'team_id' => $team->id
        ]);
        $adminRole->givePermissionTo(Permission::all());

        // Create Finance role with financial and invoice permissions
        $financeRole = Role::create([
            'name' => 'Finance',
            'team_id' => $team->id
        ]);
        $financeRole->givePermissionTo([
            'view customers',
            'edit customers',
            'view invoices',
            'create invoices',
            'edit invoices',
            'approve invoices',
            'send invoices',
            'view financial reports',
            'manage payments',
            'view payment history',
            'export financial data',
            'view team analytics',
        ]);

        // Create Employee role with basic permissions
        $employeeRole = Role::create([
            'name' => 'Employee',
            'team_id' => $team->id
        ]);
        $employeeRole->givePermissionTo([
            'view customers',
            'create customers',
            'edit customers',
            'view invoices',
            'create invoices',
            'edit invoices',
            'view vehicles',
            'create vehicles',
            'edit vehicles',
        ]);

        $this->command->info("✅ Created 3 roles for team '{$team->name}': Admin, Finance, Employee");
    }
}
