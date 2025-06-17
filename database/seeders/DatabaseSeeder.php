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

        // Get the created team
        $team = \App\Models\Team::where('name', 'Luxuria Cars LLC')->first();

        // Create user and assign to team
        User::factory()->create([
            'name' => 'Luxuria Test',
            'email' => 'test@rentluxuria.com',
            'team_id' => $team->id,
        ]);

        // User::factory(10)->create();
    }
}
