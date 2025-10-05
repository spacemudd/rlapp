<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\Location;
use App\Models\Branch;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VehicleCreateTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $team;
    protected $vehicleMake;
    protected $vehicleModel;
    protected $location;
    protected $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test team
        $this->team = Team::factory()->create();

        // Create test user
        $this->user = User::factory()->create([
            'team_id' => $this->team->id
        ]);

        // Use existing vehicle make from migration or create unique ones
        $this->vehicleMake = VehicleMake::firstOrCreate(
            ['name_en' => 'BMW'],
            [
                'name_ar' => 'بي ام دبليو',
                'team_id' => null,
            ]
        );

        $this->vehicleModel = VehicleModel::firstOrCreate(
            [
                'vehicle_make_id' => $this->vehicleMake->id,
                'name_en' => 'X5',
            ],
            [
                'name_ar' => 'اكس 5',
                'team_id' => null,
            ]
        );

        // Create test location
        $this->location = Location::factory()->create();

        // Create test branch
        $this->branch = Branch::factory()->create();
    }

    /** @test */
    public function it_can_create_a_vehicle_with_normalized_make_and_model()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'seats' => 5,
            'doors' => 4,
            'category' => 'Luxury',
            'price_daily' => 500.00,
            'price_weekly' => 3000.00,
            'price_monthly' => 12000.00,
            'location_id' => $this->location->id,
            'branch_id' => $this->branch->id,
            'status' => 'available',
            'ownership_status' => 'owned',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'recent_note' => 'Test vehicle creation',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(302); // Redirect after successful creation
        $response->assertRedirect(route('vehicles.index'));

        // Verify vehicle was created in database
        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'make' => 'BMW', // Legacy field should be populated
            'model' => 'X5', // Legacy field should be populated
            'status' => 'available',
        ]);

        // Verify the vehicle has proper relationships
        $vehicle = Vehicle::where('plate_number', 'ABC-12345')->first();
        $this->assertNotNull($vehicle);
        $this->assertEquals($this->vehicleMake->id, $vehicle->vehicle_make_id);
        $this->assertEquals($this->vehicleModel->id, $vehicle->vehicle_model_id);
        $this->assertEquals('BMW', $vehicle->make_name);
        $this->assertEquals('X5', $vehicle->model_name);
        $this->assertEquals('2023 BMW X5', $vehicle->full_name_localized);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/vehicles', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'plate_number',
            'vehicle_make_id',
            'vehicle_model_id',
            'year',
            'color',
            'category',
            'odometer',
            'chassis_number',
            'license_expiry_date',
            'insurance_expiry_date',
            'status',
            'ownership_status',
        ]);
    }

    /** @test */
    public function it_validates_unique_plate_number()
    {
        $this->actingAs($this->user);

        // Create an existing vehicle
        Vehicle::factory()->create([
            'plate_number' => 'EXISTING-123',
        ]);

        $vehicleData = [
            'plate_number' => 'EXISTING-123', // Duplicate plate number
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['plate_number']);
    }

    /** @test */
    public function it_validates_unique_chassis_number()
    {
        $this->actingAs($this->user);

        // Create an existing vehicle
        Vehicle::factory()->create([
            'chassis_number' => 'EXISTING123456789',
        ]);

        $vehicleData = [
            'plate_number' => 'NEW-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'EXISTING123456789', // Duplicate chassis number
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['chassis_number']);
    }

    /** @test */
    public function it_validates_vehicle_make_exists()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => 'non-existent-uuid',
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['vehicle_make_id']);
    }

    /** @test */
    public function it_validates_vehicle_model_exists()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => 'non-existent-uuid',
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['vehicle_model_id']);
    }

    /** @test */
    public function it_validates_year_range()
    {
        $this->actingAs($this->user);

        // Test year too old
        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 1800, // Too old
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['year']);

        // Test year too future
        $vehicleData['year'] = 2030; // Too future
        $response = $this->postJson('/vehicles', $vehicleData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['year']);
    }

    /** @test */
    public function it_validates_price_values()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'price_daily' => -100, // Negative price
            'price_weekly' => -500,
            'price_monthly' => -1000,
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price_daily', 'price_weekly', 'price_monthly']);
    }

    /** @test */
    public function it_handles_borrowed_vehicle_validation()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'borrowed',
            // Missing required fields for borrowed vehicles
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['borrowed_from_office', 'borrowing_start_date']);
    }

    /** @test */
    public function it_creates_borrowed_vehicle_with_required_fields()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'BORROW-123',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'White',
            'category' => 'Luxury',
            'odometer' => 30000,
            'chassis_number' => 'BORROW123456789',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'borrowed',
            'borrowed_from_office' => 'Partner Company',
            'borrowing_start_date' => '2024-01-01',
            'borrowing_end_date' => '2025-12-31',
            'borrowing_terms' => 'Monthly review required',
            'borrowing_notes' => 'Vehicle borrowed for fleet expansion',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(302);
        $response->assertRedirect(route('vehicles.index'));

        // Verify borrowed vehicle was created
        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'BORROW-123',
            'ownership_status' => 'borrowed',
            'borrowed_from_office' => 'Partner Company',
            'borrowing_terms' => 'Monthly review required',
        ]);
    }

    /** @test */
    public function it_validates_date_fields()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => 'invalid-date',
            'insurance_expiry_date' => 'invalid-date',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['license_expiry_date', 'insurance_expiry_date']);
    }

    /** @test */
    public function it_validates_borrowing_end_date_after_start_date()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'borrowed',
            'borrowed_from_office' => 'Partner Company',
            'borrowing_start_date' => '2025-12-31',
            'borrowing_end_date' => '2024-01-01', // End date before start date
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['borrowing_end_date']);
    }

    /** @test */
    public function it_creates_vehicle_with_minimal_required_fields()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'MIN-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Red',
            'category' => 'Economy',
            'odometer' => 0,
            'chassis_number' => 'MIN1234567890123',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(302);
        $response->assertRedirect(route('vehicles.index'));

        // Verify minimal vehicle was created
        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'MIN-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Red',
            'category' => 'Economy',
            'odometer' => 0,
        ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $vehicleData = [
            'plate_number' => 'ABC-12345',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Black',
            'category' => 'Luxury',
            'odometer' => 50000,
            'chassis_number' => 'WBAFR9C50DD123456',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_populates_legacy_make_model_fields_correctly()
    {
        $this->actingAs($this->user);

        $vehicleData = [
            'plate_number' => 'LEGACY-123',
            'vehicle_make_id' => $this->vehicleMake->id,
            'vehicle_model_id' => $this->vehicleModel->id,
            'year' => 2023,
            'color' => 'Blue',
            'category' => 'Mid-range',
            'odometer' => 25000,
            'chassis_number' => 'LEGACY123456789',
            'license_expiry_date' => '2025-12-31',
            'insurance_expiry_date' => '2025-06-30',
            'status' => 'available',
            'ownership_status' => 'owned',
        ];

        $response = $this->postJson('/vehicles', $vehicleData);

        $response->assertStatus(302);

        // Verify legacy fields are populated
        $vehicle = Vehicle::where('plate_number', 'LEGACY-123')->first();
        $this->assertEquals('BMW', $vehicle->make);
        $this->assertEquals('X5', $vehicle->model);
        $this->assertEquals($this->vehicleMake->id, $vehicle->vehicle_make_id);
        $this->assertEquals($this->vehicleModel->id, $vehicle->vehicle_model_id);
    }
}
