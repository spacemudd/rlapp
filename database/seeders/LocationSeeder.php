<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Dubai Main Office',
                'address' => 'Sheikh Zayed Road, Business Bay',
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
                'description' => 'Primary headquarters and main vehicle depot',
                'status' => 'active',
            ],
            [
                'name' => 'Dubai Marina Branch',
                'address' => 'Marina Walk, Dubai Marina',
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
                'description' => 'Marina location for luxury vehicle rentals',
                'status' => 'active',
            ],
            [
                'name' => 'Abu Dhabi Office',
                'address' => 'Corniche Road',
                'city' => 'Abu Dhabi',
                'country' => 'United Arab Emirates',
                'description' => 'Capital city branch office',
                'status' => 'active',
            ],
            [
                'name' => 'Sharjah Branch',
                'address' => 'King Faisal Street',
                'city' => 'Sharjah',
                'country' => 'United Arab Emirates',
                'description' => 'Northern Emirates service center',
                'status' => 'active',
            ],
            [
                'name' => 'Airport Terminal 3',
                'address' => 'Dubai International Airport, Terminal 3',
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
                'description' => 'Airport pickup and drop-off location',
                'status' => 'active',
            ],
            [
                'name' => 'Old Location (Closed)',
                'address' => 'Old Dubai Creek',
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
                'description' => 'Former location, now closed',
                'status' => 'inactive',
            ],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
