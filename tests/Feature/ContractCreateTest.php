<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Tests\TestCase;

class ContractCreateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Migrate only our application's migrations to avoid vendor IFRS migrations on SQLite
        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations',
        ])->run();
    }

    public function test_can_create_contract_without_mileage_and_fuel()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $vehicle = Vehicle::factory()->create([
            'price_daily' => 100,
            'price_weekly' => 600,
            'price_monthly' => 2400,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'daily_rate' => 100,
            'deposit_amount' => 0,
            // intentionally omit current_mileage and fuel_level
        ];

        $response = $this->post(route('contracts.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('contracts', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'total_days' => 2, // 1st to 3rd exclusive -> 2 days
            'pickup_mileage' => null,
            'pickup_fuel_level' => null,
        ]);
    }
}


