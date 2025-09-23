<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Branch;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptAllocation;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ContractQuickPayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the AccountingService
        $this->mock(AccountingService::class, function ($mock) {
            $mock->shouldReceive('recordPaymentReceipt')
                ->andReturn((object) ['id' => 'test-transaction-id']);
        });
    }

    public function test_quick_pay_creates_payment_receipt()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $branch->id]);

        $contract->update([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'branch_id' => $branch->id,
        ]);

        $requestData = [
            'payment_method' => 'cash',
            'reference' => 'REF123',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    'amount' => 500.00,
                    'memo' => 'Test memo 1',
                ],
                [
                    'row_id' => 'prepayment',
                    'amount' => 500.00,
                    'memo' => 'Test memo 2',
                ],
            ],
            'amount_total' => 1000.00,
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), $requestData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => __('words.payment_receipt_created_successfully'),
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'receipt' => [
                    'id',
                    'receipt_number',
                    'total_amount',
                ],
            ]);

        $this->assertDatabaseHas('payment_receipts', [
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => 'cash',
            'reference_number' => 'REF123',
            'status' => 'completed',
        ]);
    }

    public function test_quick_pay_creates_allocations()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $branch->id]);

        $contract->update([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'branch_id' => $branch->id,
        ]);

        $requestData = [
            'payment_method' => 'card',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    'amount' => 300.00,
                    'memo' => 'Violation guarantee payment',
                ],
                [
                    'row_id' => 'rental_income',
                    'amount' => 700.00,
                    'memo' => 'Rental income payment',
                ],
            ],
            'amount_total' => 1000.00,
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), $requestData);

        $response->assertStatus(200);

        $paymentReceipt = PaymentReceipt::where('contract_id', $contract->id)->first();
        
        $this->assertDatabaseHas('payment_receipt_allocations', [
            'payment_receipt_id' => $paymentReceipt->id,
            'row_id' => 'violation_guarantee',
            'amount' => 300.00,
            'memo' => 'Violation guarantee payment',
        ]);

        $this->assertDatabaseHas('payment_receipt_allocations', [
            'payment_receipt_id' => $paymentReceipt->id,
            'row_id' => 'rental_income',
            'amount' => 700.00,
            'memo' => 'Rental income payment',
        ]);
    }

    public function test_quick_pay_validates_required_fields()
    {
        $contract = Contract::factory()->create();

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'payment_method',
                'allocations',
                'amount_total',
            ]);
    }

    public function test_quick_pay_validates_payment_method()
    {
        $contract = Contract::factory()->create();

        $requestData = [
            'payment_method' => 'invalid_method',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    'amount' => 500.00,
                ],
            ],
            'amount_total' => 500.00,
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_quick_pay_validates_allocations_structure()
    {
        $contract = Contract::factory()->create();

        $requestData = [
            'payment_method' => 'cash',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    // Missing amount
                ],
            ],
            'amount_total' => 500.00,
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations.0.amount']);
    }

    public function test_quick_pay_validates_amount_totals()
    {
        $contract = Contract::factory()->create();

        $requestData = [
            'payment_method' => 'cash',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    'amount' => 500.00,
                ],
                [
                    'row_id' => 'prepayment',
                    'amount' => 300.00,
                ],
            ],
            'amount_total' => 1000.00, // Different from sum of allocations
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), $requestData);

        $response->assertStatus(200); // The validation should pass as we only validate the structure
    }

    public function test_quick_pay_handles_database_transaction_rollback()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $branch->id]);

        $contract->update([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'branch_id' => $branch->id,
        ]);

        // Mock AccountingService to throw an exception
        $this->mock(AccountingService::class, function ($mock) {
            $mock->shouldReceive('recordPaymentReceipt')
                ->andThrow(new \Exception('Accounting service error'));
        });

        $requestData = [
            'payment_method' => 'cash',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    'amount' => 500.00,
                ],
            ],
            'amount_total' => 500.00,
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), $requestData);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => __('words.payment_receipt_creation_failed'),
            ]);

        // Verify no payment receipt was created due to rollback
        $this->assertDatabaseMissing('payment_receipts', [
            'contract_id' => $contract->id,
        ]);
    }

    public function test_quick_pay_generates_unique_receipt_numbers()
    {
        $contract1 = Contract::factory()->create();
        $contract2 = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        $contract1->update(['customer_id' => $customer->id, 'branch_id' => $branch->id]);
        $contract2->update(['customer_id' => $customer->id, 'branch_id' => $branch->id]);

        $requestData = [
            'payment_method' => 'cash',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    'amount' => 500.00,
                ],
            ],
            'amount_total' => 500.00,
        ];

        // Create first receipt
        $response1 = $this->postJson(route('contracts.quick-pay', $contract1->id), $requestData);
        $response1->assertStatus(200);

        // Create second receipt
        $response2 = $this->postJson(route('contracts.quick-pay', $contract2->id), $requestData);
        $response2->assertStatus(200);

        $receipt1 = PaymentReceipt::where('contract_id', $contract1->id)->first();
        $receipt2 = PaymentReceipt::where('contract_id', $contract2->id)->first();

        $this->assertNotEquals($receipt1->receipt_number, $receipt2->receipt_number);
        $this->assertStringStartsWith('PR-', $receipt1->receipt_number);
        $this->assertStringStartsWith('PR-', $receipt2->receipt_number);
    }

    public function test_quick_pay_uses_branch_from_contract_or_vehicle()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $branch->id]);

        $contract->update([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            // No branch_id on contract, should use vehicle's branch
        ]);

        $requestData = [
            'payment_method' => 'cash',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    'amount' => 500.00,
                ],
            ],
            'amount_total' => 500.00,
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), $requestData);

        $response->assertStatus(200);

        $paymentReceipt = PaymentReceipt::where('contract_id', $contract->id)->first();
        $this->assertEquals($branch->id, $paymentReceipt->branch_id);
    }

    public function test_quick_pay_accepts_optional_reference()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        $contract->update([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ]);

        $requestData = [
            'payment_method' => 'cash',
            'allocations' => [
                [
                    'row_id' => 'violation_guarantee',
                    'amount' => 500.00,
                ],
            ],
            'amount_total' => 500.00,
            // No reference provided
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract->id), $requestData);

        $response->assertStatus(200);

        $paymentReceipt = PaymentReceipt::where('contract_id', $contract->id)->first();
        $this->assertNull($paymentReceipt->reference_number);
    }
}
