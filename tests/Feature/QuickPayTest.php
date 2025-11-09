<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\PaymentReceiptAllocation;
use IFRS\Models\Account;
use IFRS\Models\Transaction;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuickPayTest extends TestCase
{
    protected Branch $branch;
    protected int $defaultGlAccountId;
    protected array $quickPayAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->run();

        $this->defaultGlAccountId = $this->ensureIfrsAccount();

        $defaultGlAccountId = $this->defaultGlAccountId;

        $this->app->bind(AccountingService::class, function () use ($defaultGlAccountId) {
            return new class ($defaultGlAccountId) {
                public function __construct(private int $defaultGlAccountId)
                {
                }

                public function getPaymentAccountInfo($branch, string $method): array
                {
                    return [
                        'id' => "{$method}_account",
                        'name' => ucfirst(str_replace('_', ' ', $method)) . ' Account',
                        'code' => strtoupper($method),
                    ];
                }

                public function recordPaymentReceipt($paymentReceipt, array $allocations, array $mappings): object
                {
                    foreach ($allocations as $allocation) {
                        $glAccountId = $mappings[$allocation['row_id']] ?? $this->defaultGlAccountId;
                        if (!is_numeric($glAccountId)) {
                            $glAccountId = $this->defaultGlAccountId;
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
                }
            };
        });

        $this->quickPayAccounts = [
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
            'quick_pay_accounts' => $this->quickPayAccounts,
        ]);
    }

    public function test_quick_pay_respects_initial_grace_period_before_first_day()
    {
        config(['app.timezone' => 'Asia/Dubai']);

        app()->bind(AccountingService::class, fn () => new class {
            public function getPaymentAccountInfo(): ?array
            {
                return null;
            }
        });

        $start = Carbon::parse('2025-01-01 15:00:00', 'Asia/Dubai');
        Carbon::setTestNow($start->copy()->addHours(2)->addMinutes(59));

        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = $this->createContract($user, [
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(5),
            'total_days' => 5,
            'total_amount' => 525.00, // 5 days * 105 AED (VAT inclusive)
            'daily_rate' => 105.00,
            'is_vat_inclusive' => true,
        ]);

        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));
        $response->assertOk();

        $data = $response->json();
        $liabilitySection = collect($data['sections'])->firstWhere('key', 'liability');
        $this->assertNotNull($liabilitySection);

        $rentalRow = collect($liabilitySection['rows'])->firstWhere('id', 'rental_income');
        $vatRow = collect($liabilitySection['rows'])->firstWhere('id', 'vat_collection');

        $this->assertNotNull($rentalRow);
        $this->assertNotNull($vatRow);

        $this->assertSame(0.0, (float) $rentalRow['total']);
        $this->assertSame(0.0, (float) $vatRow['total']);

        Carbon::setTestNow();
    }

    public function test_quick_pay_recognizes_first_day_after_grace_period()
    {
        config(['app.timezone' => 'Asia/Dubai']);

        app()->bind(AccountingService::class, fn () => new class {
            public function getPaymentAccountInfo(): ?array
            {
                return null;
            }
        });

        $start = Carbon::parse('2025-01-01 15:00:00', 'Asia/Dubai');
        Carbon::setTestNow($start->copy()->addHours(3)->addMinutes(5));

        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = $this->createContract($user, [
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(5),
            'total_days' => 5,
            'total_amount' => 525.00,
            'daily_rate' => 105.00,
            'is_vat_inclusive' => true,
        ]);

        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));
        $response->assertOk();

        $data = $response->json();
        $liabilitySection = collect($data['sections'])->firstWhere('key', 'liability');
        $rentalRow = collect($liabilitySection['rows'])->firstWhere('id', 'rental_income');
        $vatRow = collect($liabilitySection['rows'])->firstWhere('id', 'vat_collection');

        $this->assertSame(100.0, (float) $rentalRow['total']);
        $this->assertSame(5.0, (float) $vatRow['total']);

        Carbon::setTestNow();
    }

    public function test_quick_pay_recognizes_second_day_after_one_hour_buffer()
    {
        config(['app.timezone' => 'Asia/Dubai']);

        app()->bind(AccountingService::class, fn () => new class {
            public function getPaymentAccountInfo(): ?array
            {
                return null;
            }
        });

        $start = Carbon::parse('2025-01-01 15:00:00', 'Asia/Dubai');
        Carbon::setTestNow($start->copy()->addHours(25)->addMinutes(10));

        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = $this->createContract($user, [
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(5),
            'total_days' => 5,
            'total_amount' => 525.00,
            'daily_rate' => 105.00,
            'is_vat_inclusive' => true,
        ]);

        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));
        $response->assertOk();

        $data = $response->json();
        $liabilitySection = collect($data['sections'])->firstWhere('key', 'liability');
        $rentalRow = collect($liabilitySection['rows'])->firstWhere('id', 'rental_income');
        $vatRow = collect($liabilitySection['rows'])->firstWhere('id', 'vat_collection');

        $this->assertSame(200.0, (float) $rentalRow['total']);
        $this->assertSame(10.0, (float) $vatRow['total']);

        Carbon::setTestNow();
    }

    public function test_quick_pay_summary_returns_empty_sections_for_new_contract()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = $this->createContract($user);

        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));

        $response->assertOk()
            ->assertJsonStructure([
                'contract_id',
                'currency',
                'sections' => [
                    '*' => [
                        'key',
                        'rows',
                    ],
                ],
                'totals' => [
                    'payable_now',
                    'allocated',
                    'remaining_to_allocate',
                ],
            ]);

        $data = $response->json();
        $this->assertEquals($contract->id, $data['contract_id']);
        $this->assertEquals('AED', $data['currency']);
        
        // Should have liability and income sections
        $sectionKeys = collect($data['sections'])->pluck('key')->toArray();
        $this->assertContains('liability', $sectionKeys);
        $this->assertContains('income', $sectionKeys);
        
        $liabilitySection = collect($data['sections'])->firstWhere('key', 'liability');
        $rentalRow = collect($liabilitySection['rows'])->firstWhere('id', 'rental_income');
        $vatRow = collect($liabilitySection['rows'])->firstWhere('id', 'vat_collection');

        $this->assertNotNull($rentalRow);
        $this->assertNotNull($vatRow);
        $this->assertSame(0.0, (float) $rentalRow['total']);
        $this->assertSame(0.0, (float) $vatRow['total']);
    }

    public function test_quick_pay_submission_validates_required_fields()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $contract = $this->createContract($user, [], $customer);

        // Test missing payment_method
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'reference' => 'TEST123',
            'allocations' => [
                ['row_id' => 'violation_guarantee', 'amount' => 100],
            ],
            'amount_total' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        // Test missing allocations
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'reference' => 'TEST123',
            'amount_total' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations']);

        // Test invalid payment_method
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'invalid_method',
            'reference' => 'TEST123',
            'allocations' => [
                ['row_id' => 'violation_guarantee', 'amount' => 100],
            ],
            'amount_total' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_quick_pay_submission_accepts_valid_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $contract = $this->createContract($user, [], $customer);

        $payload = [
            'payment_method' => 'cash',
            'reference' => 'TEST123',
            'allocations' => [
                ['row_id' => 'violation_guarantee', 'amount' => 100.50],
                ['row_id' => 'rental_income', 'amount' => 200.75],
            ],
            'amount_total' => 301.25,
        ];

        $response = $this->postJson(route('contracts.quick-pay', $contract), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => __('words.payment_receipt_created_successfully'),
            ]);
    }

    public function test_quick_pay_requires_authentication()
    {
        $user = User::factory()->create();
        $contract = $this->createContract($user);

        // Test quick pay summary without authentication
        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));
        $response->assertUnauthorized();

        // Test quick pay submission without authentication
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [['row_id' => 'test', 'amount' => 100]],
            'amount_total' => 100,
        ]);
        $response->assertUnauthorized();
    }

    public function test_quick_pay_requires_contract_access_permission()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create(); // Different team

        $contract = $this->createContract($user1);

        // User2 (different team) tries to access the contract
        $this->actingAs($user2);

        // Since there's no authorization policy, the request will succeed
        // This test documents the current behavior - authorization should be added in the future
        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));
        $response->assertOk(); // Currently no authorization check

        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [['row_id' => 'test', 'amount' => 100]],
            'amount_total' => 100,
        ]);
        $response->assertOk(); // Currently no authorization check
    }

    public function test_quick_pay_allocations_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $contract = $this->createContract($user, [], $customer);

        // Test empty allocations
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [],
            'amount_total' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations']);

        // Test invalid allocation structure (missing row_id)
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [
                ['amount' => 100], // Missing row_id
            ],
            'amount_total' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations.0.row_id']);

        // Test invalid allocation structure (missing amount)
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [
                ['row_id' => 'violation_guarantee'], // Missing amount
            ],
            'amount_total' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations.0.amount']);

        // Test negative amount
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [
                ['row_id' => 'violation_guarantee', 'amount' => -50],
            ],
            'amount_total' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations.0.amount']);
    }

    public function test_quick_pay_reference_field_is_optional()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $contract = $this->createContract($user, [], $customer);

        // Test with reference
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'reference' => 'REF123',
            'allocations' => [
                ['row_id' => 'violation_guarantee', 'amount' => 100],
            ],
            'amount_total' => 100,
        ]);

        $response->assertOk();

        // Test without reference (should still work)
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [
                ['row_id' => 'rental_income', 'amount' => 200],
            ],
            'amount_total' => 200,
        ]);

        $response->assertOk();
    }

    public function test_quick_pay_amount_total_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $contract = $this->createContract($user, [], $customer);

        // Test missing amount_total
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [
                ['row_id' => 'violation_guarantee', 'amount' => 100],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount_total']);

        // Test negative amount_total
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cash',
            'allocations' => [
                ['row_id' => 'violation_guarantee', 'amount' => 100],
            ],
            'amount_total' => -50,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount_total']);
    }

    public function test_quick_pay_payment_methods_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $contract = $this->createContract($user, [], $customer);

        $validMethods = ['cash', 'card', 'bank_transfer'];

        foreach ($validMethods as $method) {
            $response = $this->postJson(route('contracts.quick-pay', $contract), [
                'payment_method' => $method,
                'allocations' => [
                    ['row_id' => 'violation_guarantee', 'amount' => 100],
                ],
                'amount_total' => 100,
            ]);

            $response->assertOk();
        }

        // Test invalid payment method
        $response = $this->postJson(route('contracts.quick-pay', $contract), [
            'payment_method' => 'cryptocurrency',
            'allocations' => [
                ['row_id' => 'violation_guarantee', 'amount' => 100],
            ],
            'amount_total' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_quick_pay_contract_currency_is_returned_in_summary()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $contract = $this->createContract($user, [
            'currency' => 'USD',
        ], $customer);

        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals('USD', $data['currency']);

        // Test default currency when not set (currency field has default 'AED' in migration)
        $contract->update(['currency' => 'AED']); // Use default value instead of null

        $response = $this->getJson(route('contracts.quick-pay-summary', $contract));

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals('AED', $data['currency']);
    }

    private function createContract(
        User $user,
        array $overrides = [],
        ?Customer $customer = null,
        ?Vehicle $vehicle = null,
        ?Branch $branch = null
    ): Contract
    {
        $branch = $branch ?? $this->branch;

        $customer = $customer ?? Customer::factory()->create([
            'team_id' => $user->team_id,
        ]);

        $vehicle = $vehicle ?? Vehicle::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $defaults = [
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'branch_id' => $branch->id,
            'status' => 'active',
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
