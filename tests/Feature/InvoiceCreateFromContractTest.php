<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vehicle;
use Tests\TestCase;

class InvoiceCreateFromContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Migrate only our application's migrations to avoid vendor IFRS migrations on SQLite
        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations',
        ])->run();
    }

    public function test_can_create_invoice_from_contract()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);
        $vehicle = Vehicle::factory()->create();

        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03', // 2 days
            'total_days' => 2,
            'daily_rate' => 99,
            'total_amount' => 198,
        ]);

        $payload = [
            'invoice_date'   => '2024-01-05 10:00:00',
            'due_date'       => '2024-01-10 10:00:00',
            'total_days'     => 2,
            'start_datetime' => '2024-01-01 09:00:00',
            'end_datetime'   => '2024-01-03 09:00:00',
            'vehicle_id'     => $vehicle->id,
            'customer_id'    => $customer->id,
            'sub_total'      => 198.00,
            'total_discount' => 0.00,
            'total_amount'   => 198.00,
            'vat_rate'       => 5,
            'items'          => [
                ['description' => 'Base Rental', 'amount' => 198, 'discount' => 0],
                ['description' => 'Delivery',    'amount' => 0,   'discount' => 0],
            ],
        ];

        $response = $this->post(route('invoices.store'), $payload);
        $response->assertRedirect(route('invoices'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'vehicle_id'  => $vehicle->id,
            'sub_total'   => 198.00,
            'total_amount'=> 198.00,
        ]);

        $invoice = Invoice::latest()->with('items')->first();
        $this->assertNotNull($invoice);
        $this->assertCount(2, $invoice->items);
        $this->assertTrue($invoice->items->pluck('description')->contains('Base Rental'));
        $this->assertTrue($invoice->items->pluck('description')->contains('Delivery'));
    }
}


