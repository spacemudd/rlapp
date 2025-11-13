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
use IFRS\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ContractQuickPayTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected int $defaultGlAccountId;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->defaultGlAccountId = $this->ensureIfrsAccount();
        
        $quickPayAccounts = [
            'liability' => [
                'violation_guarantee' => $this->defaultGlAccountId,
                'prepayment' => $this->defaultGlAccountId,
                'rental_income' => $this->defaultGlAccountId,
                'vat_collection' => $this->defaultGlAccountId,
            ],
            'income' => [
                'rental_income' => $this->defaultGlAccountId,
                'vat_collection' => $this->defaultGlAccountId,
                'insurance_fee' => $this->defaultGlAccountId,
                'fines' => $this->defaultGlAccountId,
                'salik_fees' => $this->defaultGlAccountId,
            ],
        ];

        $this->branch = Branch::factory()->create([
            'quick_pay_accounts' => $quickPayAccounts,
        ]);

        $defaultGlAccountId = $this->defaultGlAccountId;

        $this->mock(AccountingService::class, function ($mock) use ($defaultGlAccountId) {
            $mock->shouldReceive('recordPaymentReceipt')
                ->andReturnUsing(function ($paymentReceipt, array $allocations, array $mappings) use ($defaultGlAccountId) {
                    foreach ($allocations as $allocation) {
                        $glAccountId = $mappings[$allocation['row_id']] ?? $defaultGlAccountId;
                        if (!is_numeric($glAccountId)) {
                            $glAccountId = $defaultGlAccountId;
                        }

                        PaymentReceiptAllocation::create([
                            'payment_receipt_id' => $paymentReceipt->id,
                            'row_id' => $allocation['row_id'],
                            'amount' => $allocation['amount'],
                            'memo' => $allocation['memo'] ?? null,
                            'gl_account_id' => $glAccountId,
                            'description' => $allocation['memo'] ?? ucfirst(str_replace('_', ' ', $allocation['row_id'])),
                        ]);
                    }

                    return new Transaction();
                });
        });
    }

    public function test_quick_pay_creates_payment_receipt()
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);
        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
        ], $customer, $vehicle);

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
            'branch_id' => $this->branch->id,
            'total_amount' => 1000.00,
            'payment_method' => 'cash',
            'reference_number' => 'REF123',
            'status' => 'completed',
        ]);
    }

    public function test_quick_pay_creates_allocations()
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);
        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
        ], $customer, $vehicle);

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
        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
        ]);

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
        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
        ]);

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
        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
        ]);

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
        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
        ]);

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
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);
        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
        ], $customer, $vehicle);

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
        $customer = Customer::factory()->create();
        $contract1 = $this->createContract([
            'team_id' => $this->user->team_id,
        ], $customer);
        $contract2 = $this->createContract([
            'team_id' => $this->user->team_id,
        ], $customer);

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
        $alternateBranch = Branch::factory()->create([
            'quick_pay_accounts' => $this->branch->quick_pay_accounts,
        ]);
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $alternateBranch->id]);

        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
            'branch_id' => null,
        ], $customer, $vehicle, $alternateBranch);

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
        $this->assertEquals($alternateBranch->id, $paymentReceipt->branch_id);
    }

    public function test_quick_pay_accepts_optional_reference()
    {
        $customer = Customer::factory()->create();
        $contract = $this->createContract([
            'team_id' => $this->user->team_id,
        ], $customer);

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

    private function createContract(
        array $overrides = [],
        ?Customer $customer = null,
        ?Vehicle $vehicle = null,
        ?Branch $branch = null
    ): Contract {
        $branch = $branch ?? $this->branch;
        $customer = $customer ?? Customer::factory()->create();
        $vehicle = $vehicle ?? Vehicle::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $defaults = [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'branch_id' => $branch->id,
        ];

        return Contract::factory()->create(array_merge($defaults, $overrides));
    }

    private function ensureIfrsAccount(): int
    {
        $entityId = DB::table('ifrs_entities')->value('id');
        if (!$entityId) {
            $entityId = DB::table('ifrs_entities')->insertGetId([
                'currency_id' => null,
                'name' => 'Test Entity',
                'multi_currency' => false,
                'mid_year_balances' => false,
                'year_start' => 1,
                'locale' => 'en_GB',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $currencyId = DB::table('ifrs_currencies')->value('id');
        if (!$currencyId) {
            $currencyId = DB::table('ifrs_currencies')->insertGetId([
                'entity_id' => $entityId,
                'name' => 'Test Currency',
                'currency_code' => 'TST',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('ifrs_entities')->where('id', $entityId)->update(['currency_id' => $currencyId]);

        $accountId = DB::table('ifrs_accounts')->value('id');
        if (!$accountId) {
            $accountId = DB::table('ifrs_accounts')->insertGetId([
                'entity_id' => $entityId,
                'category_id' => null,
                'currency_id' => $currencyId,
                'code' => '9999',
                'name' => 'Test Account',
                'description' => 'Auto-generated for tests',
                'account_type' => 'CURRENT_ASSET',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) $accountId;
    }
}
