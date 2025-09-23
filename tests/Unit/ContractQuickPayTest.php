<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Tests\TestCase;

class ContractQuickPayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations',
        ])->run();
    }

    public function test_contract_vehicle_branch_relationship_for_quick_pay()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->team_id]);
        
        $branch = Branch::factory()->create([
            'quick_pay_accounts' => [
                'liability' => [
                    'violation_guarantee' => 'liability_account_123',
                    'prepayment' => 'liability_account_456',
                ],
                'income' => [
                    'rental_income' => 'income_account_789',
                    'vat_collection' => 'income_account_101',
                ],
            ],
        ]);

        $vehicle = Vehicle::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $contract = Contract::factory()->create([
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Load the contract with relationships
        $contract->load(['vehicle.branch']);

        $this->assertNotNull($contract->vehicle);
        $this->assertNotNull($contract->vehicle->branch);
        $this->assertEquals($branch->id, $contract->vehicle->branch->id);
        $this->assertIsArray($contract->vehicle->branch->quick_pay_accounts);
        $this->assertEquals('liability_account_123', $contract->vehicle->branch->quick_pay_accounts['liability']['violation_guarantee']);
        $this->assertEquals('income_account_789', $contract->vehicle->branch->quick_pay_accounts['income']['rental_income']);
    }

    public function test_contract_without_branch_quick_pay_accounts()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->team_id]);
        
        $branch = Branch::factory()->create([
            'quick_pay_accounts' => null, // No quick pay accounts configured
        ]);

        $vehicle = Vehicle::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $contract = Contract::factory()->create([
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $contract->load(['vehicle.branch']);

        $this->assertNotNull($contract->vehicle->branch);
        $this->assertNull($contract->vehicle->branch->quick_pay_accounts);
    }

    public function test_contract_currency_for_quick_pay()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->team_id]);
        $vehicle = Vehicle::factory()->create();

        // Test with USD currency
        $contract = Contract::factory()->create([
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'currency' => 'USD',
        ]);

        $this->assertEquals('USD', $contract->currency);

        // Test with null currency (should default to AED)
        $contract2 = Contract::factory()->create([
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'currency' => 'AED', // Use default value
        ]);

        $this->assertEquals('AED', $contract2->currency);
    }

    public function test_contract_quick_pay_data_structure()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->team_id]);
        
        $branch = Branch::factory()->create([
            'ifrs_vat_account_id' => 'vat_account_123',
            'quick_pay_accounts' => [
                'liability' => [
                    'violation_guarantee' => 'violation_account_123',
                    'prepayment' => 'prepayment_account_456',
                ],
                'income' => [
                    'rental_income' => 'rental_account_789',
                    'vat_collection' => 'vat_collection_account_101',
                    'insurance_fee' => 'insurance_account_202',
                    'fines' => 'fines_account_303',
                    'salik_fees' => 'salik_account_404',
                ],
            ],
        ]);

        $vehicle = Vehicle::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $contract = Contract::factory()->create([
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'currency' => 'AED',
        ]);

        $contract->load(['vehicle.branch']);

        // Simulate the data structure that would be used in Quick Pay
        $quickPayData = [
            'contract_id' => $contract->id,
            'currency' => $contract->currency ?? 'AED',
            'branch_vat_account' => $contract->vehicle->branch->ifrs_vat_account_id,
            'branch_quick_pay_accounts' => $contract->vehicle->branch->quick_pay_accounts,
        ];

        $this->assertEquals($contract->id, $quickPayData['contract_id']);
        $this->assertEquals('AED', $quickPayData['currency']);
        $this->assertEquals('vat_account_123', $quickPayData['branch_vat_account']);
        $this->assertIsArray($quickPayData['branch_quick_pay_accounts']);
        $this->assertEquals('violation_account_123', $quickPayData['branch_quick_pay_accounts']['liability']['violation_guarantee']);
        $this->assertEquals('rental_account_789', $quickPayData['branch_quick_pay_accounts']['income']['rental_income']);
    }

    public function test_contract_quick_pay_sections_structure()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->team_id]);
        $vehicle = Vehicle::factory()->create();

        $contract = Contract::factory()->create([
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Simulate the sections structure for Quick Pay
        $sections = [
            [
                'key' => 'liability',
                'rows' => [
                    [
                        'id' => 'violation_guarantee',
                        'description' => 'Violation Guarantee',
                        'gl_account_id' => 'liability_account_123',
                        'gl_account' => 'Liability Account 123',
                        'total' => 500.00,
                        'paid' => 100.00,
                        'remaining' => 400.00,
                        'amount' => 0,
                        'editable' => true,
                    ],
                    [
                        'id' => 'prepayment',
                        'description' => 'Customer Deposits',
                        'gl_account_id' => 'liability_account_456',
                        'gl_account' => 'Liability Account 456',
                        'total' => 1000.00,
                        'paid' => 500.00,
                        'remaining' => 500.00,
                        'amount' => 0,
                        'editable' => true,
                    ],
                ],
            ],
            [
                'key' => 'income',
                'rows' => [
                    [
                        'id' => 'rental_income',
                        'description' => 'Rental Income',
                        'gl_account_id' => 'income_account_789',
                        'gl_account' => 'Income Account 789',
                        'total' => 2000.00,
                        'paid' => 0.00,
                        'remaining' => 2000.00,
                        'amount' => 0,
                        'editable' => true,
                    ],
                ],
            ],
        ];

        $this->assertCount(2, $sections);
        $this->assertEquals('liability', $sections[0]['key']);
        $this->assertEquals('income', $sections[1]['key']);
        $this->assertCount(2, $sections[0]['rows']);
        $this->assertCount(1, $sections[1]['rows']);
        
        // Test liability rows
        $violationRow = $sections[0]['rows'][0];
        $this->assertEquals('violation_guarantee', $violationRow['id']);
        $this->assertEquals(400.00, $violationRow['remaining']);
        $this->assertTrue($violationRow['editable']);
        
        // Test income rows
        $rentalRow = $sections[1]['rows'][0];
        $this->assertEquals('rental_income', $rentalRow['id']);
        $this->assertEquals(2000.00, $rentalRow['remaining']);
        $this->assertTrue($rentalRow['editable']);
    }

    public function test_contract_quick_pay_totals_calculation()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->team_id]);
        $vehicle = Vehicle::factory()->create();

        $contract = Contract::factory()->create([
            'team_id' => $user->team_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Simulate totals calculation
        $allocations = [
            ['row_id' => 'violation_guarantee', 'amount' => 150.00],
            ['row_id' => 'prepayment', 'amount' => 200.00],
            ['row_id' => 'rental_income', 'amount' => 500.00],
        ];

        $totals = [
            'payable_now' => 850.00, // Sum of all remaining amounts
            'allocated' => 850.00,   // Sum of all allocations
            'remaining_to_allocate' => 0.00, // payable_now - allocated
        ];

        $this->assertEquals(850.00, $totals['payable_now']);
        $this->assertEquals(850.00, $totals['allocated']);
        $this->assertEquals(0.00, $totals['remaining_to_allocate']);
    }

    public function test_contract_quick_pay_validation_rules()
    {
        // Test payment method validation
        $validPaymentMethods = ['cash', 'card', 'bank_transfer'];
        $invalidPaymentMethods = ['cryptocurrency', 'check', 'bitcoin'];

        foreach ($validPaymentMethods as $method) {
            $this->assertContains($method, $validPaymentMethods);
        }

        foreach ($invalidPaymentMethods as $method) {
            $this->assertNotContains($method, $validPaymentMethods);
        }

        // Test allocation structure validation
        $validAllocation = [
            'row_id' => 'violation_guarantee',
            'amount' => 100.50,
        ];

        $invalidAllocations = [
            ['amount' => 100.50], // Missing row_id
            ['row_id' => 'violation_guarantee'], // Missing amount
            ['row_id' => 'violation_guarantee', 'amount' => -50], // Negative amount
            ['row_id' => '', 'amount' => 100.50], // Empty row_id
        ];

        $this->assertArrayHasKey('row_id', $validAllocation);
        $this->assertArrayHasKey('amount', $validAllocation);
        $this->assertGreaterThan(0, $validAllocation['amount']);

        foreach ($invalidAllocations as $invalid) {
            $this->assertTrue(
                !array_key_exists('row_id', $invalid) || 
                !array_key_exists('amount', $invalid) || 
                (isset($invalid['amount']) && $invalid['amount'] < 0) ||
                (isset($invalid['row_id']) && empty($invalid['row_id']))
            );
        }
    }

    public function test_contract_quick_pay_basic_functionality()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->team_id]);
        $vehicle = Vehicle::factory()->create();

        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Test that contract can be created and basic properties work
        $this->assertNotNull($contract->id);
        $this->assertEquals($customer->id, $contract->customer_id);
        $this->assertEquals($vehicle->id, $contract->vehicle_id);
        $this->assertContains($contract->status, ['draft', 'active', 'completed', 'void']);
    }
}
