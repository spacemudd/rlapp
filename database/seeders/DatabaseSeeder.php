<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create teams first
        $this->call([
            TeamsSeeder::class,
        ]);

        // Seed sources
        $this->call([
            SourceSeeder::class,
        ]);

        // Initialize IFRS accounting system (must be done early for proper setup)
        $this->call([
            IFRSSeeder::class,
        ]);

        // Create team roles and permissions for all teams
        $this->call([
            TeamRolesSeeder::class,
        ]);

        // Get the created team
        $team = \App\Models\Team::where('name', 'Luxuria Cars LLC')->first();

        // Get the default IFRS entity and assign it to the team
        $entity = \IFRS\Models\Entity::first();
        if ($entity && !$team->entity_id) {
            $team->update(['entity_id' => $entity->id]);
        }

        // Create user and assign to team
        $user = User::factory()->create([
            'name' => 'Luxuria Test',
            'email' => 'test@rentluxuria.com',
            'team_id' => $team->id,
        ]);

        // Set team context and assign Admin role to the test user
        setPermissionsTeamId($team->id);
        $user->assignRole('Admin');

        // Create sample customers
        $this->call([
            CustomersSeeder::class,
        ]);

        // User::factory(10)->create();

        $this->call([
            VehicleSeeder::class,
        ]);
    }
}
