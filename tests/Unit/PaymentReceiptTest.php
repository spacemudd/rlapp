<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptAllocation;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Branch;
use App\Models\Bank;
use App\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_payment_receipt()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        $paymentReceipt = PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'reference_number' => 'REF123',
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
            'notes' => 'Test payment receipt',
            'created_by' => 'Test User',
        ]);

        $this->assertDatabaseHas('payment_receipts', [
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'status' => 'completed',
        ]);

        $this->assertEquals('PR-000001', $paymentReceipt->receipt_number);
        $this->assertEquals(1000.00, $paymentReceipt->total_amount);
        $this->assertEquals(PaymentReceipt::METHOD_CASH, $paymentReceipt->payment_method);
    }

    public function test_generates_unique_receipt_number()
    {
        // Create first receipt
        $contract1 = Contract::factory()->create();
        $customer1 = Customer::factory()->create();
        $branch1 = Branch::factory()->create();

        $receipt1 = PaymentReceipt::create([
            'receipt_number' => PaymentReceipt::generateReceiptNumber(),
            'contract_id' => $contract1->id,
            'customer_id' => $customer1->id,
            'branch_id' => $branch1->id,
            'total_amount' => 500.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        // Create second receipt
        $contract2 = Contract::factory()->create();
        $customer2 = Customer::factory()->create();
        $branch2 = Branch::factory()->create();

        $receipt2 = PaymentReceipt::create([
            'receipt_number' => PaymentReceipt::generateReceiptNumber(),
            'contract_id' => $contract2->id,
            'customer_id' => $customer2->id,
            'branch_id' => $branch2->id,
            'total_amount' => 750.00,
            'payment_method' => PaymentReceipt::METHOD_CARD,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $this->assertNotEquals($receipt1->receipt_number, $receipt2->receipt_number);
        $this->assertStringStartsWith('PR-', $receipt1->receipt_number);
        $this->assertStringStartsWith('PR-', $receipt2->receipt_number);
    }

    public function test_belongs_to_contract()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

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

        $this->assertInstanceOf(Contract::class, $paymentReceipt->contract);
        $this->assertEquals($contract->id, $paymentReceipt->contract->id);
    }

    public function test_belongs_to_customer()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

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

        $this->assertInstanceOf(Customer::class, $paymentReceipt->customer);
        $this->assertEquals($customer->id, $paymentReceipt->customer->id);
    }

    public function test_belongs_to_branch()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

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

        $this->assertInstanceOf(Branch::class, $paymentReceipt->branch);
        $this->assertEquals($branch->id, $paymentReceipt->branch->id);
    }

    public function test_has_many_allocations()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

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

        // Create allocations
        $allocation1 = PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-1',
            'row_id' => 'violation_guarantee',
            'description' => 'Violation Guarantee',
            'amount' => 500.00,
            'memo' => 'Test memo 1',
        ]);

        $allocation2 = PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-2',
            'row_id' => 'prepayment',
            'description' => 'Prepayment',
            'amount' => 500.00,
            'memo' => 'Test memo 2',
        ]);

        $this->assertCount(2, $paymentReceipt->allocations);
        $this->assertInstanceOf(PaymentReceiptAllocation::class, $paymentReceipt->allocations->first());
    }

    public function test_payment_method_constants()
    {
        $this->assertEquals('cash', PaymentReceipt::METHOD_CASH);
        $this->assertEquals('card', PaymentReceipt::METHOD_CARD);
        $this->assertEquals('bank_transfer', PaymentReceipt::METHOD_BANK_TRANSFER);
    }

    public function test_get_payment_methods()
    {
        $methods = PaymentReceipt::getPaymentMethods();

        $this->assertArrayHasKey(PaymentReceipt::METHOD_CASH, $methods);
        $this->assertArrayHasKey(PaymentReceipt::METHOD_CARD, $methods);
        $this->assertArrayHasKey(PaymentReceipt::METHOD_BANK_TRANSFER, $methods);

        $this->assertEquals('Cash', $methods[PaymentReceipt::METHOD_CASH]);
        $this->assertEquals('Card', $methods[PaymentReceipt::METHOD_CARD]);
        $this->assertEquals('Bank Transfer', $methods[PaymentReceipt::METHOD_BANK_TRANSFER]);
    }

    public function test_payment_method_label_accessor()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

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

        $this->assertEquals('Cash', $paymentReceipt->payment_method_label);
    }

    public function test_scope_completed()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        // Create completed receipt
        PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        // Create pending receipt
        PaymentReceipt::create([
            'receipt_number' => 'PR-000002',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 500.00,
            'payment_method' => PaymentReceipt::METHOD_CARD,
            'payment_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $completedReceipts = PaymentReceipt::completed()->get();

        $this->assertCount(1, $completedReceipts);
        $this->assertEquals('completed', $completedReceipts->first()->status);
    }

    public function test_scope_pending()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        // Create completed receipt
        PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        // Create pending receipt
        PaymentReceipt::create([
            'receipt_number' => 'PR-000002',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 500.00,
            'payment_method' => PaymentReceipt::METHOD_CARD,
            'payment_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $pendingReceipts = PaymentReceipt::pending()->get();

        $this->assertCount(1, $pendingReceipts);
        $this->assertEquals('pending', $pendingReceipts->first()->status);
    }

    public function test_scope_failed()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        // Create completed receipt
        PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        // Create failed receipt
        PaymentReceipt::create([
            'receipt_number' => 'PR-000002',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 500.00,
            'payment_method' => PaymentReceipt::METHOD_CARD,
            'payment_date' => now()->toDateString(),
            'status' => 'failed',
        ]);

        $failedReceipts = PaymentReceipt::failed()->get();

        $this->assertCount(1, $failedReceipts);
        $this->assertEquals('failed', $failedReceipts->first()->status);
    }

    public function test_casts_attributes()
    {
        $contract = Contract::factory()->create();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        $paymentReceipt = PaymentReceipt::create([
            'receipt_number' => 'PR-000001',
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.50,
            'payment_method' => PaymentReceipt::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $this->assertIsFloat($paymentReceipt->total_amount);
        $this->assertEquals(1000.50, $paymentReceipt->total_amount);
        $this->assertInstanceOf(\Carbon\Carbon::class, $paymentReceipt->payment_date);
    }
}
