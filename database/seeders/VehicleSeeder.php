<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'plate_number' => 'ABC123',
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2023,
                'color' => 'Silver',
                'category' => 'Sedan',
                'current_location' => 'Dubai Marina',
                'status' => 'available',
                'odometer' => 15000,
                'chassis_number' => 'JTDKN3DU7D5012345',
                'license_expiry_date' => Carbon::now()->addYear(),
                'insurance_expiry_date' => Carbon::now()->addYear(),
                'recent_note' => 'Regular maintenance completed',
            ],
            [
                'plate_number' => 'XYZ789',
                'make' => 'Honda',
                'model' => 'CR-V',
                'year' => 2023,
                'color' => 'Black',
                'category' => 'SUV',
                'current_location' => 'Downtown Dubai',
                'status' => 'available',
                'odometer' => 12000,
                'chassis_number' => '5J6RW2H89ML123456',
                'license_expiry_date' => Carbon::now()->addYear(),
                'insurance_expiry_date' => Carbon::now()->addYear(),
                'recent_note' => 'New tires installed',
            ],
            [
                'plate_number' => 'DEF456',
                'make' => 'BMW',
                'model' => 'X5',
                'year' => 2023,
                'color' => 'White',
                'category' => 'Luxury SUV',
                'current_location' => 'Palm Jumeirah',
                'status' => 'rented',
                'expected_return_date' => Carbon::now()->addDays(3),
                'odometer' => 8000,
                'chassis_number' => 'WBAJA0C51BC123456',
                'license_expiry_date' => Carbon::now()->addYear(),
                'insurance_expiry_date' => Carbon::now()->addYear(),
                'recent_note' => 'Currently rented',
            ],
            [
                'plate_number' => 'GHI789',
                'make' => 'Mercedes-Benz',
                'model' => 'E-Class',
                'year' => 2023,
                'color' => 'Blue',
                'category' => 'Luxury Sedan',
                'current_location' => 'Business Bay',
                'status' => 'maintenance',
                'odometer' => 20000,
                'chassis_number' => 'WDDHF5KB5EA123456',
                'license_expiry_date' => Carbon::now()->addYear(),
                'insurance_expiry_date' => Carbon::now()->addYear(),
                'recent_note' => 'Scheduled maintenance',
            ],
            [
                'plate_number' => 'JKL012',
                'make' => 'Audi',
                'model' => 'Q7',
                'year' => 2023,
                'color' => 'Gray',
                'category' => 'Premium SUV',
                'current_location' => 'Dubai Mall',
                'status' => 'available',
                'odometer' => 5000,
                'chassis_number' => 'WAUZZZF4XMN123456',
                'license_expiry_date' => Carbon::now()->addYear(),
                'insurance_expiry_date' => Carbon::now()->addYear(),
                'recent_note' => 'Recently detailed',
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}
