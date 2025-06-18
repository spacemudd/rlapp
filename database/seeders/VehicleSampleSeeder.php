<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class VehicleSampleSeeder extends Seeder
{
    /**
     * Run the database seedsssssss.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'plate_number' => 'B-41551',
                'make' => 'Hyundai',
                'model' => 'Elantra',
                'year' => 2022,
                'color' => 'Black',
                'category' => 'Economy',
                'current_location' => 'Dubai Marina',
                'status' => 'available',
                'odometer' => 0,
                'chassis_number' => 'KMHLS4AG4NU260858',
                'license_expiry_date' => Carbon::now()->addYear(),
                'insurance_expiry_date' => Carbon::now()->addYear(),
                'recent_note' => '',
                'price_daily' => 129,
                'price_weekly' => 749,
                'price_monthly' => 2799,
                'seats' => 5,
                'doors' => 4,
                'expected_return_date' => null,
                'upcoming_reservations' => 0,
                'latest_return_date' => null,
            ],
            [
                'plate_number' => 'A-47501',
                'make' => 'Chevrolet',
                'model' => '"	Malibu"',
                'year' => 2023,
                'color' => 'White',
                'category' => 'Economy',
                'current_location' => 'Downtown Dubai',
                'status' => 'available',
                'odometer' => 0,
                'chassis_number' => '1G1ZD5ST0PF132739',
                'license_expiry_date' => Carbon::now()->addYear(),
                'insurance_expiry_date' => Carbon::now()->addYear(),
                'recent_note' => 'New tires installed',
                'price_daily' => 129,
                'price_weekly' => 749,
                'price_monthly' => 2799,
                'seats' => 5,
                'doors' => 4,
                'expected_return_date' => null,
                'upcoming_reservations' => 1,
                'latest_return_date' => null,
            ],
            [
                'plate_number' => '238-15',
                'make' => 'Nissan',
                'model' => 'Altima',
                'year' => 2022,
                'color' => 'Gray',
                'category' => 'Economy',
                'current_location' => 'Palm Jumeirah',
                'status' => 'rented',
                'odometer' => 8000,
                'chassis_number' => '1G1ZD5ST8PF208515',
                'license_expiry_date' => Carbon::now()->addYear(),
                'insurance_expiry_date' => Carbon::now()->addYear(),
                'recent_note' => 'Currently rented',
                'price_daily' => 129,
                'price_weekly' => 749,
                'price_monthly' => 2799,
                'seats' => 5,
                'doors' => 4,
                'expected_return_date' => Carbon::now()->addDays(3),
                'upcoming_reservations' => 2,
                'latest_return_date' => Carbon::now()->subDays(10),
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}
