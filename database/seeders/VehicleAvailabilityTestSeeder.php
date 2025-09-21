<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\User;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Contract;
use App\Models\Reservation;
use Carbon\Carbon;

class VehicleAvailabilityTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = now()->format('YmdHis');
        
        // Create test team
        $team = Team::factory()->create([
            'name' => "Test Car Rental Team {$timestamp}",
            'description' => 'Test team for vehicle availability testing'
        ]);

        // Create test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => "test-vehicle-availability-{$timestamp}@example.com",
            'team_id' => $team->id
        ]);

        // Create test customers
        $customers = [
            Customer::factory()->create([
                'team_id' => $team->id,
                'first_name' => 'Ahmed',
                'last_name' => 'Al-Rashid',
                'email' => "ahmed-test-{$timestamp}@example.com",
                'phone' => '+971501234567'
            ]),
            Customer::factory()->create([
                'team_id' => $team->id,
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => "sarah-test-{$timestamp}@example.com",
                'phone' => '+971507654321'
            ]),
            Customer::factory()->create([
                'team_id' => $team->id,
                'first_name' => 'Mohammed',
                'last_name' => 'Hassan',
                'email' => "mohammed-test-{$timestamp}@example.com",
                'phone' => '+971509876543'
            ])
        ];

        // Create test vehicles
        $vehicles = [
            // BMW X5 - Available
            Vehicle::factory()->create([
                'make' => 'BMW',
                'model' => 'X5',
                'year' => 2023,
                'plate_number' => "TEST-{$timestamp}-001",
                'price_daily' => 500.00,
                'price_weekly' => 3000.00,
                'price_monthly' => 12000.00,
                'is_active' => true,
                'status' => 'available',
                'category' => 'SUV'
            ]),
            
            // Mercedes C-Class - Available
            Vehicle::factory()->create([
                'make' => 'Mercedes',
                'model' => 'C-Class',
                'year' => 2023,
                'plate_number' => "TEST-{$timestamp}-002",
                'price_daily' => 400.00,
                'price_weekly' => 2400.00,
                'price_monthly' => 9600.00,
                'is_active' => true,
                'status' => 'available',
                'category' => 'Sedan'
            ]),
            
            // Audi A4 - Available
            Vehicle::factory()->create([
                'make' => 'Audi',
                'model' => 'A4',
                'year' => 2023,
                'plate_number' => "TEST-{$timestamp}-003",
                'price_daily' => 350.00,
                'price_weekly' => 2100.00,
                'price_monthly' => 8400.00,
                'is_active' => true,
                'status' => 'available',
                'category' => 'Sedan'
            ]),
            
            // BMW 3 Series - Available
            Vehicle::factory()->create([
                'make' => 'BMW',
                'model' => '3 Series',
                'year' => 2023,
                'plate_number' => "TEST-{$timestamp}-004",
                'price_daily' => 450.00,
                'price_weekly' => 2700.00,
                'price_monthly' => 10800.00,
                'is_active' => true,
                'status' => 'available',
                'category' => 'Sedan'
            ]),
            
            // Toyota Camry - Available
            Vehicle::factory()->create([
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2023,
                'plate_number' => "TEST-{$timestamp}-005",
                'price_daily' => 300.00,
                'price_weekly' => 1800.00,
                'price_monthly' => 7200.00,
                'is_active' => true,
                'status' => 'available',
                'category' => 'Sedan'
            ]),
            
            // Range Rover - Available
            Vehicle::factory()->create([
                'make' => 'Range Rover',
                'model' => 'Evoque',
                'year' => 2023,
                'plate_number' => "TEST-{$timestamp}-006",
                'price_daily' => 600.00,
                'price_weekly' => 3600.00,
                'price_monthly' => 14400.00,
                'is_active' => true,
                'status' => 'available',
                'category' => 'SUV'
            ])
        ];

        // Create test contracts with different scenarios
        $contracts = [
            // Active contract: BMW X5 from Jan 1-5, 2025
            Contract::factory()->create([
                'vehicle_id' => $vehicles[0]->id, // BMW X5
                'customer_id' => $customers[0]->id, // Ahmed
                'status' => 'active',
                'start_date' => Carbon::parse('2025-01-01 10:00:00'),
                'end_date' => Carbon::parse('2025-01-05 10:00:00'),
                'contract_number' => "CT-{$timestamp}-001",
                'daily_rate' => 500.00,
                'total_days' => 4,
                'total_amount' => 2000.00
            ]),
            
            // Active contract: Mercedes C-Class from Jan 10-15, 2025
            Contract::factory()->create([
                'vehicle_id' => $vehicles[1]->id, // Mercedes C-Class
                'customer_id' => $customers[1]->id, // Sarah
                'status' => 'active',
                'start_date' => Carbon::parse('2025-01-10 10:00:00'),
                'end_date' => Carbon::parse('2025-01-15 10:00:00'),
                'contract_number' => "CT-{$timestamp}-002",
                'daily_rate' => 400.00,
                'total_days' => 5,
                'total_amount' => 2000.00
            ]),
            
            // Completed contract: Audi A4 (should not affect availability)
            Contract::factory()->create([
                'vehicle_id' => $vehicles[2]->id, // Audi A4
                'customer_id' => $customers[2]->id, // Mohammed
                'status' => 'completed',
                'start_date' => Carbon::parse('2024-12-20 10:00:00'),
                'end_date' => Carbon::parse('2024-12-25 10:00:00'),
                'contract_number' => "CT-{$timestamp}-100",
                'daily_rate' => 350.00,
                'total_days' => 5,
                'total_amount' => 1750.00
            ])
        ];

        // Create test reservations with different scenarios
        $reservations = [
            // Confirmed reservation: BMW 3 Series from Jan 8-12, 2025
            Reservation::factory()->create([
                'vehicle_id' => $vehicles[3]->id, // BMW 3 Series
                'customer_id' => $customers[1]->id, // Sarah
                'status' => 'confirmed',
                'pickup_date' => Carbon::parse('2025-01-08 10:00:00'),
                'return_date' => Carbon::parse('2025-01-12 10:00:00'),
                'uid' => "RES-{$timestamp}-001",
                'rate' => 450.00,
                'duration_days' => 4,
                'total_amount' => 1800.00
            ]),
            
            // Pending reservation: Toyota Camry (should not affect availability)
            Reservation::factory()->create([
                'vehicle_id' => $vehicles[4]->id, // Toyota Camry
                'customer_id' => $customers[2]->id, // Mohammed
                'status' => 'pending',
                'pickup_date' => Carbon::parse('2025-01-20 10:00:00'),
                'return_date' => Carbon::parse('2025-01-25 10:00:00'),
                'uid' => "RES-{$timestamp}-002",
                'rate' => 300.00,
                'duration_days' => 5,
                'total_amount' => 1500.00
            ]),
            
            // Canceled reservation: Range Rover (should not affect availability)
            Reservation::factory()->create([
                'vehicle_id' => $vehicles[5]->id, // Range Rover
                'customer_id' => $customers[0]->id, // Ahmed
                'status' => 'canceled',
                'pickup_date' => Carbon::parse('2025-01-15 10:00:00'),
                'return_date' => Carbon::parse('2025-01-20 10:00:00'),
                'uid' => "RES-{$timestamp}-003",
                'rate' => 600.00,
                'duration_days' => 5,
                'total_amount' => 3000.00
            ])
        ];

        $this->command->info('Vehicle Availability Test Data Created:');
        $this->command->info('- 1 Team');
        $this->command->info('- 1 User');
        $this->command->info('- 3 Customers');
        $this->command->info('- 6 Vehicles');
        $this->command->info('- 3 Contracts (2 active, 1 completed)');
        $this->command->info('- 3 Reservations (1 confirmed, 1 pending, 1 canceled)');
        $this->command->info('');
        $this->command->info('Test Scenarios:');
        $this->command->info('- BMW X5: Unavailable Jan 1-5 (Active Contract)');
        $this->command->info('- Mercedes C-Class: Unavailable Jan 10-15 (Active Contract)');
        $this->command->info('- BMW 3 Series: Unavailable Jan 8-12 (Confirmed Reservation)');
        $this->command->info('- Audi A4: Available (Completed Contract)');
        $this->command->info('- Toyota Camry: Available (Pending Reservation)');
        $this->command->info('- Range Rover: Available (Canceled Reservation)');
    }
}
