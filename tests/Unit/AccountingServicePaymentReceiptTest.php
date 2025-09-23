<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptAllocation;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Branch;
use App\Models\Vehicle;
use App\Services\AccountingService;
use IFRS\Models\Account;
use IFRS\Models\Transaction;
use IFRS\Models\LineItem;
use IFRS\Models\Entity;
use IFRS\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AccountingServicePaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up IFRS entities and currencies
        $this->setupIfrsData();
    }

    private function setupIfrsData()
    {
        // Create test entity
        $entity = Entity::create([
            'name' => 'Test Entity',
            'currency_id' => Currency::create(['name' => 'AED', 'currency_code' => 'AED'])->id,
        ]);

        // Create test accounts
        $cashAccount = Account::create([
            'name' => 'Cash Account',
            'account_type' => Account::CURRENT_ASSET,
            'entity_id' => $entity->id,
        ]);

        $bankAccount = Account::create([
            'name' => 'Bank Account',
            'account_type' => Account::CURRENT_ASSET,
            'entity_id' => $entity->id,
        ]);

        $violationAccount = Account::create([
            'name' => 'Violation Guarantee',
            'account_type' => Account::CURRENT_LIABILITY,
            'entity_id' => $entity->id,
        ]);

        $prepaymentAccount = Account::create([
            'name' => 'Prepayment',
            'account_type' => Account::CURRENT_LIABILITY,
            'entity_id' => $entity->id,
        ]);

        $incomeAccount = Account::create([
            'name' => 'Rental Income',
            'account_type' => Account::OPERATING_REVENUE,
            'entity_id' => $entity->id,
        ]);

        // Mock the AccountingService methods
        $this->mock(AccountingService::class, function ($mock) use ($entity, $cashAccount, $bankAccount) {
            $mock->shouldReceive('getCurrentEntity')->andReturn($entity);
            $mock->shouldReceive('getDefaultCurrency')->andReturn($entity->currency);
            $mock->shouldReceive('getBranchCashAccount')->andReturn($cashAccount);
            $mock->shouldReceive('getBranchBankAccount')->andReturn($bankAccount);
        });
    }

    public function test_record_payment_receipt_creates_ifrs_transaction()
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

        $paymentReceipt = PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $allocations = [
            [
                'row_id' => 'violation_guarantee',
                'amount' => 500.00,
                'memo' => 'Violation guarantee payment',
            ],
            [
                'row_id' => 'prepayment',
                'amount' => 500.00,
                'memo' => 'Prepayment',
            ],
        ];

        $accountMappings = [
            'violation_guarantee' => Account::where('name', 'Violation Guarantee')->first()->id,
            'prepayment' => Account::where('name', 'Prepayment')->first()->id,
        ];

        $accountingService = new AccountingService();
        
        // Mock the private methods
        $accountingService = Mockery::mock(AccountingService::class)->makePartial();
        $accountingService->shouldReceive('getCurrentEntity')->andReturn(Entity::first());
        $accountingService->shouldReceive('getDefaultCurrency')->andReturn(Currency::first());
        $accountingService->shouldReceive('getPaymentAccountForMethod')->andReturn(Account::where('name', 'Cash Account')->first());
        $accountingService->shouldReceive('getDescriptionForRowId')->andReturn('Test Description');

        $transaction = $accountingService->recordPaymentReceipt($paymentReceipt, $allocations, $accountMappings);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertDatabaseHas('ifrs_transactions', [
            'id' => $transaction->id,
            'transaction_type' => Transaction::JN,
        ]);

        // Verify payment receipt was updated with transaction ID
        $paymentReceipt->refresh();
        $this->assertEquals($transaction->id, $paymentReceipt->ifrs_transaction_id);
    }

    public function test_record_payment_receipt_creates_line_items()
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

        $paymentReceipt = PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $allocations = [
            [
                'row_id' => 'violation_guarantee',
                'amount' => 500.00,
                'memo' => 'Violation guarantee payment',
            ],
            [
                'row_id' => 'prepayment',
                'amount' => 500.00,
                'memo' => 'Prepayment',
            ],
        ];

        $accountMappings = [
            'violation_guarantee' => Account::where('name', 'Violation Guarantee')->first()->id,
            'prepayment' => Account::where('name', 'Prepayment')->first()->id,
        ];

        $accountingService = Mockery::mock(AccountingService::class)->makePartial();
        $accountingService->shouldReceive('getCurrentEntity')->andReturn(Entity::first());
        $accountingService->shouldReceive('getDefaultCurrency')->andReturn(Currency::first());
        $accountingService->shouldReceive('getPaymentAccountForMethod')->andReturn(Account::where('name', 'Cash Account')->first());
        $accountingService->shouldReceive('getDescriptionForRowId')->andReturn('Test Description');

        $transaction = $accountingService->recordPaymentReceipt($paymentReceipt, $allocations, $accountMappings);

        // Verify cash account debit line item
        $this->assertDatabaseHas('ifrs_line_items', [
            'transaction_id' => $transaction->id,
            'account_id' => Account::where('name', 'Cash Account')->first()->id,
            'amount' => 1000.00,
            'credited' => false, // Debit
        ]);

        // Verify allocation line items
        $this->assertDatabaseHas('ifrs_line_items', [
            'transaction_id' => $transaction->id,
            'account_id' => Account::where('name', 'Violation Guarantee')->first()->id,
            'amount' => 500.00,
            'credited' => true, // Credit
        ]);

        $this->assertDatabaseHas('ifrs_line_items', [
            'transaction_id' => $transaction->id,
            'account_id' => Account::where('name', 'Prepayment')->first()->id,
            'amount' => 500.00,
            'credited' => true, // Credit
        ]);
    }

    public function test_record_payment_receipt_creates_allocations()
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

        $paymentReceipt = PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $allocations = [
            [
                'row_id' => 'violation_guarantee',
                'amount' => 500.00,
                'memo' => 'Violation guarantee payment',
            ],
            [
                'row_id' => 'prepayment',
                'amount' => 500.00,
                'memo' => 'Prepayment',
            ],
        ];

        $accountMappings = [
            'violation_guarantee' => Account::where('name', 'Violation Guarantee')->first()->id,
            'prepayment' => Account::where('name', 'Prepayment')->first()->id,
        ];

        $accountingService = Mockery::mock(AccountingService::class)->makePartial();
        $accountingService->shouldReceive('getCurrentEntity')->andReturn(Entity::first());
        $accountingService->shouldReceive('getDefaultCurrency')->andReturn(Currency::first());
        $accountingService->shouldReceive('getPaymentAccountForMethod')->andReturn(Account::where('name', 'Cash Account')->first());
        $accountingService->shouldReceive('getDescriptionForRowId')->andReturn('Test Description');

        $transaction = $accountingService->recordPaymentReceipt($paymentReceipt, $allocations, $accountMappings);

        // Verify payment receipt allocations were created
        $this->assertDatabaseHas('payment_receipt_allocations', [
            'payment_receipt_id' => $paymentReceipt->id,
            'row_id' => 'violation_guarantee',
            'amount' => 500.00,
            'memo' => 'Violation guarantee payment',
        ]);

        $this->assertDatabaseHas('payment_receipt_allocations', [
            'payment_receipt_id' => $paymentReceipt->id,
            'row_id' => 'prepayment',
            'amount' => 500.00,
            'memo' => 'Prepayment',
        ]);
    }

    public function test_record_payment_receipt_handles_zero_amount_allocations()
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

        $paymentReceipt = PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 500.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $allocations = [
            [
                'row_id' => 'violation_guarantee',
                'amount' => 500.00,
                'memo' => 'Violation guarantee payment',
            ],
            [
                'row_id' => 'prepayment',
                'amount' => 0.00, // Zero amount allocation
                'memo' => 'Prepayment',
            ],
        ];

        $accountMappings = [
            'violation_guarantee' => Account::where('name', 'Violation Guarantee')->first()->id,
            'prepayment' => Account::where('name', 'Prepayment')->first()->id,
        ];

        $accountingService = Mockery::mock(AccountingService::class)->makePartial();
        $accountingService->shouldReceive('getCurrentEntity')->andReturn(Entity::first());
        $accountingService->shouldReceive('getDefaultCurrency')->andReturn(Currency::first());
        $accountingService->shouldReceive('getPaymentAccountForMethod')->andReturn(Account::where('name', 'Cash Account')->first());
        $accountingService->shouldReceive('getDescriptionForRowId')->andReturn('Test Description');

        $transaction = $accountingService->recordPaymentReceipt($paymentReceipt, $allocations, $accountMappings);

        // Verify only non-zero allocation was created
        $this->assertDatabaseHas('payment_receipt_allocations', [
            'payment_receipt_id' => $paymentReceipt->id,
            'row_id' => 'violation_guarantee',
            'amount' => 500.00,
        ]);

        $this->assertDatabaseMissing('payment_receipt_allocations', [
            'payment_receipt_id' => $paymentReceipt->id,
            'row_id' => 'prepayment',
            'amount' => 0.00,
        ]);
    }

    public function test_record_payment_receipt_throws_exception_for_missing_account_mapping()
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

        $paymentReceipt = PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $allocations = [
            [
                'row_id' => 'unknown_row_id',
                'amount' => 500.00,
                'memo' => 'Test memo',
            ],
        ];

        $accountMappings = [
            'violation_guarantee' => Account::where('name', 'Violation Guarantee')->first()->id,
            // Missing mapping for 'unknown_row_id'
        ];

        $accountingService = Mockery::mock(AccountingService::class)->makePartial();
        $accountingService->shouldReceive('getCurrentEntity')->andReturn(Entity::first());
        $accountingService->shouldReceive('getDefaultCurrency')->andReturn(Currency::first());
        $accountingService->shouldReceive('getPaymentAccountForMethod')->andReturn(Account::where('name', 'Cash Account')->first());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No GL account mapping found for row_id: unknown_row_id');

        $accountingService->recordPaymentReceipt($paymentReceipt, $allocations, $accountMappings);
    }

    public function test_record_payment_receipt_rolls_back_on_error()
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

        $paymentReceipt = PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $allocations = [
            [
                'row_id' => 'violation_guarantee',
                'amount' => 500.00,
                'memo' => 'Violation guarantee payment',
            ],
        ];

        $accountMappings = [
            'violation_guarantee' => Account::where('name', 'Violation Guarantee')->first()->id,
        ];

        // Mock to throw exception during line item creation
        $accountingService = Mockery::mock(AccountingService::class)->makePartial();
        $accountingService->shouldReceive('getCurrentEntity')->andReturn(Entity::first());
        $accountingService->shouldReceive('getDefaultCurrency')->andReturn(Currency::first());
        $accountingService->shouldReceive('getPaymentAccountForMethod')->andReturn(Account::where('name', 'Cash Account')->first());
        $accountingService->shouldReceive('getDescriptionForRowId')->andThrow(new \Exception('Database error'));

        $this->expectException(\Exception::class);

        try {
            $accountingService->recordPaymentReceipt($paymentReceipt, $allocations, $accountMappings);
        } catch (\Exception $e) {
            // Verify no line items were created due to rollback
            $this->assertDatabaseMissing('ifrs_line_items', [
                'transaction_id' => $paymentReceipt->ifrs_transaction_id,
            ]);

            throw $e;
        }
    }
}
