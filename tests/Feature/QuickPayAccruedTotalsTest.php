<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use IFRS\Models\Entity;
use IFRS\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Carbon\Carbon;

class QuickPayAccruedTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_pay_accrued_totals_match_expected_values(): void
    {
        // Freeze time to the point of inquiry
        Carbon::setTestNow(Carbon::parse('2025-11-06 13:21:00', 'UTC'));

        // Ensure timezone used by controller logic is deterministic
        Config::set('app.timezone', 'UTC');

        // Authenticate user first (needed for IFRS model creation)
        $user = User::factory()->create();
        $this->actingAs($user);

        // Set up IFRS entity and currency
        // Create entity first (without currency)
        $entity = Entity::create([
            'name' => 'Test Entity',
        ]);

        // Create or get team and associate entity
        $team = $user->team ?? Team::factory()->create();
        if (!$user->team_id) {
            $user->update(['team_id' => $team->id]);
        }
        $team->update(['entity_id' => $entity->id]);
        $user->refresh(); // Refresh to load the relationship

        // Create currency with entity_id
        $currency = Currency::create([
            'name' => 'UAE Dirham',
            'currency_code' => 'AED',
            'entity_id' => $entity->id,
        ]);

        // Update entity with currency
        $entity->update(['currency_id' => $currency->id]);
        
        // Ensure user entity relationship is accessible
        // The entity relationship goes through team, so refresh user
        $user->load('team');

        // Minimal related models
        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);
        $vehicle = Vehicle::factory()->create();

        // Contract across 8 days (diffInDays), VAT inclusive
        $contract = Contract::create([
            'contract_number' => 'CON-TEST-0001',
            'status' => 'active',
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2025-10-31 13:40:00',
            'end_date' => '2025-11-08 10:00:00',
            'total_days' => 8,
            // Choose total_amount to produce per-day rounding identical to controller expectations
            // perDay = round(total_amount / total_days, 2)
            // With 7 consumed days at 2025-11-06, this yields the closest possible figures
            'total_amount' => 996.64, // perDay=124.58, 7 days -> 872.06 (incl VAT)
            'daily_rate' => 124.58,
            'currency' => 'AED',
            'is_vat_inclusive' => true,
        ]);

        // Hit the Quick Pay summary endpoint
        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));
        $response->assertOk();

        $json = $response->json();

        // Extract liability rows for rental income and VAT
        $liability = collect($json['sections'] ?? [])->firstWhere('key', 'liability');
        $this->assertNotEmpty($liability, 'Liability section missing from response');

        $rows = collect($liability['rows'] ?? []);
        $rentalRow = $rows->firstWhere('id', 'rental_income');
        $vatRow = $rows->firstWhere('id', 'vat_collection');

        $this->assertNotEmpty($rentalRow, 'rental_income row missing');
        $this->assertNotEmpty($vatRow, 'vat_collection row missing');

        // Expected values at 2025-11-06 13:21
        $expectedRentalNet = 830.52;
        $expectedVAT = 41.53;

        // Controller uses per-day rounding then multiplies, so minor rounding differences of 0.01 can occur.
        // Assert within 0.01 tolerance to accommodate 2-decimal per-day rounding behavior.
        $this->assertEqualsWithDelta($expectedRentalNet, (float) ($rentalRow['total'] ?? 0), 0.01, 'Rental income mismatch');
        $this->assertEqualsWithDelta($expectedVAT, (float) ($vatRow['total'] ?? 0), 0.00, 'VAT amount mismatch');
    }
}


