<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Models\Contract;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class VehicleSelectionComponentTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $team;
    protected $customer;
    protected $vehicle1;
    protected $vehicle2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test team
        $this->team = Team::factory()->create();

        // Create test user
        $this->user = User::factory()->create([
            'team_id' => $this->team->id
        ]);

        // Create test customer
        $this->customer = Customer::factory()->create([
            'team_id' => $this->team->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Al-Rashid'
        ]);

        // Create test vehicles
        $this->vehicle1 = Vehicle::factory()->create([
            'make' => 'BMW',
            'model' => 'X5',
            'year' => 2023,
            'plate_number' => 'ABC-123',
            'price_daily' => 500.00,
            'is_active' => true,
            'status' => 'available',
            'category' => 'Luxury'
        ]);

        $this->vehicle2 = Vehicle::factory()->create([
            'make' => 'Mercedes',
            'model' => 'C-Class',
            'year' => 2023,
            'plate_number' => 'DEF-456',
            'price_daily' => 400.00,
            'is_active' => true,
            'status' => 'available',
            'category' => 'Luxury'
        ]);
    }

    /** @test */
    public function it_loads_reservation_create_page_with_vehicle_selection()
    {
        $this->actingAs($this->user);

        $response = $this->get('/reservations/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Reservations/Create')
        );
    }

    /** @test */
    public function it_returns_vehicles_with_availability_when_dates_are_provided()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'BMW'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();

        $this->assertCount(1, $vehicles);
        $this->assertEquals('BMW', $vehicles[0]['make']);
        $this->assertEquals('available', $vehicles[0]['availability']);
        $this->assertFalse($vehicles[0]['disabled']);
    }

    /** @test */
    public function it_returns_unavailable_vehicles_with_conflict_details()
    {
        $this->actingAs($this->user);

        // Create an active contract
        $contract = Contract::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(3),
            'contract_number' => 'CT-2025-001'
        ]);

        $pickupDate = Carbon::now()->addDays(2)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(4)->format('Y-m-d H:i:s');

        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'BMW'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();

        $this->assertCount(1, $vehicles);
        $this->assertEquals('unavailable', $vehicles[0]['availability']);
        $this->assertTrue($vehicles[0]['disabled']);
        $this->assertNotNull($vehicles[0]['conflict']);
        $this->assertEquals('CT-2025-001', $vehicles[0]['conflict']['contract_number']);
        $this->assertEquals('Ahmed Al-Rashid', $vehicles[0]['conflict']['customer_name']);
    }

    /** @test */
    public function it_returns_similar_vehicles_when_original_is_unavailable()
    {
        $this->actingAs($this->user);

        // Create an active contract for BMW
        $contract = Contract::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(3),
            'contract_number' => 'CT-2025-001'
        ]);

        $pickupDate = Carbon::now()->addDays(2)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(4)->format('Y-m-d H:i:s');

        $response = $this->postJson('/vehicle-availability/similar', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();

        // Should return Mercedes (similar category)
        $this->assertCount(1, $vehicles);
        $this->assertEquals('Mercedes', $vehicles[0]['make']);
        $this->assertEquals('available', $vehicles[0]['availability']);
    }

    /** @test */
    public function it_handles_empty_search_results()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'NonExistentVehicle'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();

        $this->assertCount(0, $vehicles);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => Carbon::now()->addDays(1)->format('Y-m-d H:i:s'),
            'return_date' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s')
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_validates_date_parameters()
    {
        $this->actingAs($this->user);

        // Test with invalid date format
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => 'invalid-date',
            'return_date' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s')
        ]);

        $response->assertStatus(422);

        // Test with return_date before pickup_date
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
            'return_date' => Carbon::now()->addDays(1)->format('Y-m-d H:i:s')
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_handles_multiple_conflicts_correctly()
    {
        $this->actingAs($this->user);

        // Create multiple contracts for the same vehicle
        $contract1 = Contract::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(2),
            'contract_number' => 'CT-2025-001'
        ]);

        $contract2 = Contract::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => Carbon::now()->addDays(4),
            'end_date' => Carbon::now()->addDays(6),
            'contract_number' => 'CT-2025-002'
        ]);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(7)->format('Y-m-d H:i:s');

        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertFalse($data['available']);
        $this->assertCount(2, $data['conflicts']);
        
        $contractNumbers = collect($data['conflicts'])->pluck('contract_number')->toArray();
        $this->assertContains('CT-2025-001', $contractNumbers);
        $this->assertContains('CT-2025-002', $contractNumbers);
    }
}
