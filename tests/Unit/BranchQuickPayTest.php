<?php

namespace Tests\Unit;

use App\Models\Branch;
use Tests\TestCase;

class BranchQuickPayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations',
        ])->run();
    }

    public function test_branch_can_store_quick_pay_accounts()
    {
        $branch = Branch::factory()->create([
            'quick_pay_accounts' => [
                'liability' => [
                    'violation_guarantee' => 'account_123',
                    'prepayment' => 'account_456',
                ],
                'income' => [
                    'rental_income' => 'account_789',
                    'vat_collection' => 'account_101',
                    'insurance_fee' => 'account_202',
                    'fines' => 'account_303',
                    'salik_fees' => 'account_404',
                ],
            ],
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
        ]);

        $branch->refresh();
        $this->assertIsArray($branch->quick_pay_accounts);
        $this->assertArrayHasKey('liability', $branch->quick_pay_accounts);
        $this->assertArrayHasKey('income', $branch->quick_pay_accounts);
        $this->assertEquals('account_123', $branch->quick_pay_accounts['liability']['violation_guarantee']);
        $this->assertEquals('account_789', $branch->quick_pay_accounts['income']['rental_income']);
    }

    public function test_branch_quick_pay_accounts_can_be_empty()
    {
        $branch = Branch::factory()->create([
            'quick_pay_accounts' => null,
        ]);

        $branch->refresh();
        $this->assertNull($branch->quick_pay_accounts);
    }

    public function test_branch_quick_pay_accounts_can_be_partial()
    {
        $branch = Branch::factory()->create([
            'quick_pay_accounts' => [
                'liability' => [
                    'violation_guarantee' => 'account_123',
                ],
                'income' => [], // Empty income section
            ],
        ]);

        $branch->refresh();
        $this->assertIsArray($branch->quick_pay_accounts);
        $this->assertArrayHasKey('violation_guarantee', $branch->quick_pay_accounts['liability']);
        $this->assertEmpty($branch->quick_pay_accounts['income']);
    }

    public function test_branch_quick_pay_accounts_casts_to_array()
    {
        $quickPayAccounts = [
            'liability' => [
                'violation_guarantee' => 'account_123',
                'prepayment' => 'account_456',
            ],
            'income' => [
                'rental_income' => 'account_789',
                'vat_collection' => 'account_101',
            ],
        ];

        $branch = Branch::factory()->create([
            'quick_pay_accounts' => $quickPayAccounts,
        ]);

        $branch->refresh();
        
        // Should be automatically cast to array
        $this->assertIsArray($branch->quick_pay_accounts);
        $this->assertIsArray($branch->quick_pay_accounts['liability']);
        $this->assertIsArray($branch->quick_pay_accounts['income']);
        
        // Should maintain the structure
        $this->assertEquals('account_123', $branch->quick_pay_accounts['liability']['violation_guarantee']);
        $this->assertEquals('account_789', $branch->quick_pay_accounts['income']['rental_income']);
    }

    public function test_branch_quick_pay_accounts_validation_structure()
    {
        $branch = Branch::factory()->create();

        // Test updating with valid structure
        $validData = [
            'liability' => [
                'violation_guarantee' => 'acc_123',
                'prepayment' => 'acc_456',
            ],
            'income' => [
                'rental_income' => 'acc_789',
                'vat_collection' => 'acc_101',
                'insurance_fee' => 'acc_202',
                'fines' => 'acc_303',
                'salik_fees' => 'acc_404',
            ],
        ];

        $branch->update(['quick_pay_accounts' => $validData]);
        $branch->refresh();

        $this->assertEquals($validData, $branch->quick_pay_accounts);
    }

    public function test_branch_quick_pay_accounts_handles_missing_sections()
    {
        $branch = Branch::factory()->create([
            'quick_pay_accounts' => [
                'liability' => [
                    'violation_guarantee' => 'account_123',
                ],
                // Missing income section
            ],
        ]);

        $branch->refresh();
        
        $this->assertArrayHasKey('liability', $branch->quick_pay_accounts);
        $this->assertArrayNotHasKey('income', $branch->quick_pay_accounts);
    }

    public function test_branch_quick_pay_accounts_with_vat_account()
    {
        $branch = Branch::factory()->create([
            'ifrs_vat_account_id' => 'vat_account_123',
            'quick_pay_accounts' => [
                'liability' => [
                    'violation_guarantee' => 'liability_account_123',
                ],
                'income' => [
                    'vat_collection' => 'vat_collection_account_456',
                ],
            ],
        ]);

        $branch->refresh();

        $this->assertEquals('vat_account_123', $branch->ifrs_vat_account_id);
        $this->assertEquals('liability_account_123', $branch->quick_pay_accounts['liability']['violation_guarantee']);
        $this->assertEquals('vat_collection_account_456', $branch->quick_pay_accounts['income']['vat_collection']);
    }

    public function test_branch_quick_pay_accounts_can_be_updated()
    {
        $branch = Branch::factory()->create([
            'quick_pay_accounts' => [
                'liability' => [
                    'violation_guarantee' => 'old_account_123',
                ],
                'income' => [],
            ],
        ]);

        // Update with new data
        $newData = [
            'liability' => [
                'violation_guarantee' => 'new_account_456',
                'prepayment' => 'prepayment_account_789',
            ],
            'income' => [
                'rental_income' => 'rental_account_101',
                'vat_collection' => 'vat_account_202',
            ],
        ];

        $branch->update(['quick_pay_accounts' => $newData]);
        $branch->refresh();

        $this->assertEquals($newData, $branch->quick_pay_accounts);
        $this->assertEquals('new_account_456', $branch->quick_pay_accounts['liability']['violation_guarantee']);
        $this->assertEquals('rental_account_101', $branch->quick_pay_accounts['income']['rental_income']);
    }

    public function test_branch_quick_pay_accounts_serialization()
    {
        $originalData = [
            'liability' => [
                'violation_guarantee' => 'account_123',
                'prepayment' => 'account_456',
            ],
            'income' => [
                'rental_income' => 'account_789',
                'vat_collection' => 'account_101',
                'insurance_fee' => 'account_202',
                'fines' => 'account_303',
                'salik_fees' => 'account_404',
            ],
        ];

        $branch = Branch::factory()->create([
            'quick_pay_accounts' => $originalData,
        ]);

        // Test that data survives serialization/deserialization
        $serialized = json_encode($branch->toArray());
        $deserialized = json_decode($serialized, true);
        
        $this->assertEquals($originalData, $deserialized['quick_pay_accounts']);
    }

    public function test_branch_quick_pay_accounts_empty_object_handling()
    {
        // Test with empty objects (as sent from frontend)
        $emptyData = [
            'liability' => [],
            'income' => [],
        ];

        $branch = Branch::factory()->create([
            'quick_pay_accounts' => $emptyData,
        ]);

        $branch->refresh();
        
        $this->assertIsArray($branch->quick_pay_accounts);
        $this->assertEmpty($branch->quick_pay_accounts['liability']);
        $this->assertEmpty($branch->quick_pay_accounts['income']);
    }
}
