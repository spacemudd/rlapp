<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Models\Contract;
use App\Models\Reservation;
use App\Models\Team;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class VehicleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $team;
    protected $customer;
    protected $vehicle1;
    protected $vehicle2;
    protected $vehicle3;
    protected $bmwMake;
    protected $mercedesMake;
    protected $audiMake;
    protected $bmwX5Model;
    protected $mercedesCClassModel;
    protected $audiA4Model;

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

        // Create vehicle makes
        $this->bmwMake = VehicleMake::factory()->bmw()->create([
            'team_id' => $this->team->id
        ]);

        $this->mercedesMake = VehicleMake::factory()->mercedes()->create([
            'team_id' => $this->team->id
        ]);

        $this->audiMake = VehicleMake::factory()->audi()->create([
            'team_id' => $this->team->id
        ]);

        // Create vehicle models
        $this->bmwX5Model = VehicleModel::factory()->bmwX5()->create([
            'vehicle_make_id' => $this->bmwMake->id,
            'team_id' => $this->team->id
        ]);

        $this->mercedesCClassModel = VehicleModel::factory()->mercedesCClass()->create([
            'vehicle_make_id' => $this->mercedesMake->id,
            'team_id' => $this->team->id
        ]);

        $this->audiA4Model = VehicleModel::factory()->audiA4()->create([
            'vehicle_make_id' => $this->audiMake->id,
            'team_id' => $this->team->id
        ]);

        // Create test vehicles
        $this->vehicle1 = Vehicle::factory()->create([
            'make' => 'BMW',
            'model' => 'X5',
            'vehicle_make_id' => $this->bmwMake->id,
            'vehicle_model_id' => $this->bmwX5Model->id,
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
            'vehicle_make_id' => $this->mercedesMake->id,
            'vehicle_model_id' => $this->mercedesCClassModel->id,
            'year' => 2023,
            'plate_number' => 'DEF-456',
            'price_daily' => 400.00,
            'is_active' => true,
            'status' => 'available',
            'category' => 'Luxury'
        ]);

        $this->vehicle3 = Vehicle::factory()->create([
            'make' => 'Audi',
            'model' => 'A4',
            'vehicle_make_id' => $this->audiMake->id,
            'vehicle_model_id' => $this->audiA4Model->id,
            'year' => 2023,
            'plate_number' => 'GHI-789',
            'price_daily' => 350.00,
            'is_active' => true,
            'status' => 'available',
            'category' => 'Luxury'
        ]);
    }

    /** @test */
    public function it_can_search_vehicles_with_availability_status()
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
        $response->assertJsonStructure([
            '*' => [
                'id',
                'label',
                'value',
                'make',
                'model',
                'year',
                'plate_number',
                'price_daily',
                'availability',
                'conflict',
                'disabled'
            ]
        ]);

        // Should find BMW vehicle
        $vehicles = $response->json();
        $this->assertCount(1, $vehicles);
        $this->assertEquals('BMW', $vehicles[0]['make']);
        $this->assertEquals('available', $vehicles[0]['availability']);
    }

    /** @test */
    public function it_shows_unavailable_vehicles_with_conflict_details()
    {
        $this->actingAs($this->user);

        // Create an active contract for vehicle1
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
        $this->assertNotNull($vehicles[0]['conflict']);
        $this->assertEquals('CT-2025-001', $vehicles[0]['conflict']['contract_number']);
        $this->assertEquals('Ahmed Al-Rashid', $vehicles[0]['conflict']['customer_name']);
    }

    /** @test */
    public function it_checks_vehicle_availability_for_specific_dates()
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

        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
            'conflicts' => [
                [
                    'type' => 'contract',
                    'contract_number' => 'CT-2025-001',
                    'customer_name' => 'Ahmed Al-Rashid'
                ]
            ]
        ]);
    }

    /** @test */
    public function it_returns_available_vehicles_when_no_conflicts()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(10)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(15)->format('Y-m-d H:i:s');

        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
            'conflicts' => []
        ]);
    }

    /** @test */
    public function it_handles_reservation_conflicts()
    {
        $this->actingAs($this->user);

        // Create a confirmed reservation
        $reservation = Reservation::factory()->create([
            'vehicle_id' => $this->vehicle2->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'pickup_date' => Carbon::now()->addDays(1),
            'return_date' => Carbon::now()->addDays(3),
            'uid' => 'RES-12345678'
        ]);

        $pickupDate = Carbon::now()->addDays(2)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(4)->format('Y-m-d H:i:s');

        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle2->id,
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
            'conflicts' => [
                [
                    'type' => 'reservation',
                    'contract_number' => 'RES-12345678',
                    'customer_name' => 'Ahmed Al-Rashid'
                ]
            ]
        ]);
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

        // Should return other available vehicles (Mercedes and Audi)
        $this->assertCount(2, $vehicles);
        
        $makes = collect($vehicles)->pluck('make')->toArray();
        $this->assertContains('Mercedes', $makes);
        $this->assertContains('Audi', $makes);
        $this->assertNotContains('BMW', $makes);
    }

    /** @test */
    public function it_validates_required_parameters()
    {
        $this->actingAs($this->user);

        // Test missing pickup_date
        $response = $this->postJson('/vehicle-availability/search', [
            'return_date' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s')
        ]);

        $response->assertStatus(422);

        // Test missing return_date
        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => Carbon::now()->addDays(1)->format('Y-m-d H:i:s')
        ]);

        $response->assertStatus(422);

        // Test invalid vehicle_id
        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => 'invalid-id',
            'pickup_date' => Carbon::now()->addDays(1)->format('Y-m-d H:i:s'),
            'return_date' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s')
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_handles_date_range_overlaps_correctly()
    {
        $this->actingAs($this->user);

        // Create contract: Jan 1-5
        $contract = Contract::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => Carbon::parse('2025-01-01 10:00:00'),
            'end_date' => Carbon::parse('2025-01-05 10:00:00'),
            'contract_number' => 'CT-2025-001'
        ]);

        // Test 1: Request Jan 3-7 (overlaps with contract)
        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => '2025-01-03 10:00:00',
            'return_date' => '2025-01-07 10:00:00'
        ]);

        $response->assertStatus(200);
        $response->assertJson(['available' => false]);

        // Test 2: Request Jan 6-10 (no overlap)
        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => '2025-01-06 10:00:00',
            'return_date' => '2025-01-10 10:00:00'
        ]);

        $response->assertStatus(200);
        $response->assertJson(['available' => true]);

        // Test 3: Request Dec 28 - Jan 2 (overlaps with contract)
        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => '2024-12-28 10:00:00',
            'return_date' => '2025-01-02 10:00:00'
        ]);

        $response->assertStatus(200);
        $response->assertJson(['available' => false]);
    }

    /** @test */
    public function it_ignores_inactive_contracts_and_reservations()
    {
        $this->actingAs($this->user);

        // Create inactive contract
        $contract = Contract::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed', // inactive status
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(3),
            'contract_number' => 'CT-2025-001'
        ]);

        // Create canceled reservation
        $reservation = Reservation::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'customer_id' => $this->customer->id,
            'status' => 'canceled', // inactive status
            'pickup_date' => Carbon::now()->addDays(1),
            'return_date' => Carbon::now()->addDays(3),
            'uid' => 'RES-12345678'
        ]);

        $pickupDate = Carbon::now()->addDays(2)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(4)->format('Y-m-d H:i:s');

        $response = $this->postJson('/vehicle-availability/check', [
            'vehicle_id' => $this->vehicle1->id,
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
            'conflicts' => []
        ]);
    }

    /** @test */
    public function it_can_search_vehicles_by_arabic_make_name()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        // Test searching for BMW using Arabic name "بي ام"
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'بي ام'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();
        $this->assertCount(1, $vehicles);
        $this->assertEquals('BMW', $vehicles[0]['make']);
        $this->assertEquals('X5', $vehicles[0]['model']);
        $this->assertEquals('available', $vehicles[0]['availability']);
    }

    /** @test */
    public function it_can_search_vehicles_by_arabic_make_name_full()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        // Test searching for BMW using full Arabic name "بي ام دبليو"
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'بي ام دبليو'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();
        $this->assertCount(1, $vehicles);
        $this->assertEquals('BMW', $vehicles[0]['make']);
        $this->assertEquals('X5', $vehicles[0]['model']);
        $this->assertEquals('available', $vehicles[0]['availability']);
    }

    /** @test */
    public function it_can_search_vehicles_by_arabic_model_name()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        // Test searching for X5 using Arabic model name "اكس 5"
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'اكس 5'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();
        $this->assertCount(1, $vehicles);
        $this->assertEquals('BMW', $vehicles[0]['make']);
        $this->assertEquals('X5', $vehicles[0]['model']);
        $this->assertEquals('available', $vehicles[0]['availability']);
    }

    /** @test */
    public function it_can_search_vehicles_by_mercedes_arabic_name()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        // Test searching for Mercedes using Arabic name "مرسيدس"
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'مرسيدس'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();
        $this->assertCount(1, $vehicles);
        $this->assertEquals('Mercedes-Benz', $vehicles[0]['make']);
        $this->assertEquals('C-Class', $vehicles[0]['model']);
        $this->assertEquals('available', $vehicles[0]['availability']);
    }

    /** @test */
    public function it_can_search_vehicles_by_audi_arabic_name()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        // Test searching for Audi using Arabic name "أودي"
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'أودي'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();
        $this->assertCount(1, $vehicles);
        $this->assertEquals('Audi', $vehicles[0]['make']);
        $this->assertEquals('A4', $vehicles[0]['model']);
        $this->assertEquals('available', $vehicles[0]['availability']);
    }

    /** @test */
    public function it_returns_localized_names_based_on_app_locale()
    {
        $this->actingAs($this->user);

        // Set app locale to Arabic
        app()->setLocale('ar');

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
        
        // Should return Arabic names when locale is Arabic
        $this->assertEquals('بي ام دبليو', $vehicles[0]['make']);
        $this->assertEquals('اكس 5', $vehicles[0]['model']);
        $this->assertStringContainsString('بي ام دبليو', $vehicles[0]['label']);
        $this->assertStringContainsString('اكس 5', $vehicles[0]['label']);

        // Reset locale to English
        app()->setLocale('en');
    }

    /** @test */
    public function it_handles_partial_arabic_search_correctly()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        // Test partial Arabic search for BMW "بي"
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'بي'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();
        $this->assertCount(1, $vehicles);
        $this->assertEquals('BMW', $vehicles[0]['make']);
    }

    /** @test */
    public function it_returns_empty_results_for_non_existent_arabic_search()
    {
        $this->actingAs($this->user);

        $pickupDate = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $returnDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        // Test searching for non-existent Arabic term
        $response = $this->postJson('/vehicle-availability/search', [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'query' => 'سيارة غير موجودة'
        ]);

        $response->assertStatus(200);
        $vehicles = $response->json();
        $this->assertCount(0, $vehicles);
    }
}
