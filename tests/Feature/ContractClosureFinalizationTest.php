<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\PaymentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractClosureFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $team;
    protected $customer;
    protected $vehicle;
    protected $contract;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->team = Team::factory()->create();
        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->actingAs($this->user);

        $this->customer = Customer::factory()->create([
            'team_id' => $this->team->id,
        ]);
        
        $this->vehicle = Vehicle::factory()->create([
            'team_id' => $this->team->id,
        ]);

        $this->contract = Contract::factory()->create([
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'team_id' => $this->team->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(2),
            'total_days' => 3,
            'daily_rate' => 100,
            'total_amount' => 300,
            'status' => 'active',
        ]);
    }

    public function test_can_access_closure_preparation_page()
    {
        $response = $this->get(route('contracts.prepare-closure', $this->contract));
        
        $response->assertOk();
        $response->assertInertia(fn ($page) => 
            $page->component('Contracts/ClosureReview')
                ->has('contract')
                ->has('summary')
                ->has('invoiceItems')
        );
    }

    public function test_can_finalize_contract_closure_with_invoice_items()
    {
        // Mock the AccountingService to avoid IFRS dependency issues
        $mockAccountingService = $this->createMock(\App\Services\AccountingService::class);
        $mockAccountingService->method('recordInvoice')->willReturn(true);
        $mockAccountingService->method('applyAdvancesToInvoice')->willReturn(null);
        $mockAccountingService->method('processDepositRefund')->willReturnCallback(function() { return; });
        
        $this->app->instance(\App\Services\AccountingService::class, $mockAccountingService);

        $invoiceItems = [
            [
                'description' => 'Base Rental',
                'quantity' => 3,
                'unit_price' => 100,
                'amount' => 300,
            ],
        ];

        $response = $this->post(route('contracts.finalize-closure', $this->contract), [
            'invoice_items' => $invoiceItems,
            'refund_deposit' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Check invoice was created
        $this->assertDatabaseHas('invoices', [
            'contract_id' => $this->contract->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'total_days' => 3,
            'team_id' => $this->team->id,
        ]);

        // Check invoice items were created
        $invoice = Invoice::where('contract_id', $this->contract->id)->first();
        $this->assertNotNull($invoice);
        $this->assertCount(1, $invoice->items);
        
        // Check contract status updated
        $this->contract->refresh();
        $this->assertEquals('completed', $this->contract->status);
        $this->assertNotNull($this->contract->completed_at);
    }

    public function test_requires_invoice_items_to_finalize()
    {
        $response = $this->post(route('contracts.finalize-closure', $this->contract), [
            'invoice_items' => [],
        ]);

        $response->assertSessionHasErrors(['invoice_items']);
    }

    public function test_validates_invoice_item_structure()
    {
        $response = $this->post(route('contracts.finalize-closure', $this->contract), [
            'invoice_items' => [
                ['description' => ''], // Missing amount
            ],
        ]);

        $response->assertSessionHasErrors();
    }
}
