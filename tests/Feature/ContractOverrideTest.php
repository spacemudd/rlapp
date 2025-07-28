<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a user and team
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create a customer
        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);
        
        // Create a vehicle
        $vehicle = Vehicle::factory()->create([
            'price_daily' => 100,
            'price_weekly' => 600,
            'price_monthly' => 2400,
        ]);
        
        $this->customer = $customer;
        $this->vehicle = $vehicle;
    }

    public function test_daily_rate_override()
    {
        $response = $this->post(route('contracts.store'), [
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'daily_rate' => 150, // Override to 150 AED/day
            'deposit_amount' => 500,
            'override_daily_rate' => true,
            'override_reason' => 'Special customer discount',
            'current_mileage' => 50000,
            'fuel_level' => 'full',
        ]);

        $response->assertRedirect();
        
        $contract = Contract::latest()->first();
        
        $this->assertTrue($contract->override_daily_rate);
        $this->assertFalse($contract->override_final_price);
        $this->assertEquals(150, $contract->daily_rate);
        $this->assertEquals(450, $contract->total_amount); // 3 days × 150 AED
        $this->assertEquals('Special customer discount', $contract->override_reason);
    }

    public function test_final_price_override()
    {
        $response = $this->post(route('contracts.store'), [
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'daily_rate' => 100, // This will be recalculated
            'deposit_amount' => 500,
            'override_final_price' => true,
            'final_price_override' => 400, // Override total to 400 AED
            'override_reason' => 'Bulk booking discount',
            'current_mileage' => 50000,
            'fuel_level' => 'full',
        ]);

        $response->assertRedirect();
        
        $contract = Contract::latest()->first();
        
        $this->assertFalse($contract->override_daily_rate);
        $this->assertTrue($contract->override_final_price);
        $this->assertEquals(400, $contract->total_amount);
        $this->assertEquals(133.33, round($contract->daily_rate, 2)); // 400 / 3 days
        $this->assertEquals('Bulk booking discount', $contract->override_reason);
    }

    public function test_no_override_uses_calculated_pricing()
    {
        $response = $this->post(route('contracts.store'), [
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'daily_rate' => 100,
            'deposit_amount' => 500,
            'current_mileage' => 50000,
            'fuel_level' => 'full',
        ]);

        $response->assertRedirect();
        
        $contract = Contract::latest()->first();
        
        $this->assertFalse($contract->override_daily_rate);
        $this->assertFalse($contract->override_final_price);
        $this->assertEquals(100, $contract->daily_rate);
        $this->assertEquals(300, $contract->total_amount); // 3 days × 100 AED
        $this->assertNotNull($contract->original_calculated_amount);
    }

    public function test_override_percentage_calculation()
    {
        $contract = Contract::create([
            'contract_number' => 'CON-000001',
            'team_id' => $this->actingAs(User::factory()->create())->user->team_id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'daily_rate' => 150,
            'total_days' => 3,
            'total_amount' => 450,
            'original_calculated_amount' => 300,
            'override_daily_rate' => true,
            'status' => 'draft',
        ]);

        $this->assertEquals(50.0, $contract->getOverridePercentage()); // 50% increase
        $this->assertEquals(150, $contract->getOverrideDifference()); // 150 AED difference
        $this->assertTrue($contract->isOverrideMarkup());
        $this->assertFalse($contract->isOverrideDiscount());
    }

    public function test_override_discount_calculation()
    {
        $contract = Contract::create([
            'contract_number' => 'CON-000002',
            'team_id' => $this->actingAs(User::factory()->create())->user->team_id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'daily_rate' => 50,
            'total_days' => 3,
            'total_amount' => 150,
            'original_calculated_amount' => 300,
            'override_daily_rate' => true,
            'status' => 'draft',
        ]);

        $this->assertEquals(50.0, $contract->getOverridePercentage()); // 50% decrease
        $this->assertEquals(-150, $contract->getOverrideDifference()); // -150 AED difference
        $this->assertFalse($contract->isOverrideMarkup());
        $this->assertTrue($contract->isOverrideDiscount());
    }
} 