<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeamsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Team::create([
            'name' => 'Luxuria Cars LLC',
            'description' => 'A premium automotive company specializing in luxury vehicles and exceptional customer service.',
        ]);
    }
} 