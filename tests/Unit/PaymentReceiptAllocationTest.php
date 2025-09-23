<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptAllocation;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_payment_receipt_allocation()
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

        $allocation = PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-123',
            'row_id' => 'violation_guarantee',
            'description' => 'Violation Guarantee',
            'amount' => 500.00,
            'memo' => 'Test memo',
        ]);

        $this->assertDatabaseHas('payment_receipt_allocations', [
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-123',
            'row_id' => 'violation_guarantee',
            'description' => 'Violation Guarantee',
            'amount' => 500.00,
            'memo' => 'Test memo',
        ]);

        $this->assertEquals($paymentReceipt->id, $allocation->payment_receipt_id);
        $this->assertEquals('test-account-123', $allocation->gl_account_id);
        $this->assertEquals('violation_guarantee', $allocation->row_id);
        $this->assertEquals(500.00, $allocation->amount);
    }

    public function test_belongs_to_payment_receipt()
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

        $allocation = PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-123',
            'row_id' => 'violation_guarantee',
            'description' => 'Violation Guarantee',
            'amount' => 500.00,
        ]);

        $this->assertInstanceOf(PaymentReceipt::class, $allocation->paymentReceipt);
        $this->assertEquals($paymentReceipt->id, $allocation->paymentReceipt->id);
    }

    public function test_scope_for_row_id()
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

        // Create violation guarantee allocation
        PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-1',
            'row_id' => 'violation_guarantee',
            'description' => 'Violation Guarantee',
            'amount' => 500.00,
        ]);

        // Create prepayment allocation
        PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-2',
            'row_id' => 'prepayment',
            'description' => 'Prepayment',
            'amount' => 500.00,
        ]);

        $violationAllocations = PaymentReceiptAllocation::forRowId('violation_guarantee')->get();
        $prepaymentAllocations = PaymentReceiptAllocation::forRowId('prepayment')->get();

        $this->assertCount(1, $violationAllocations);
        $this->assertCount(1, $prepaymentAllocations);
        $this->assertEquals('violation_guarantee', $violationAllocations->first()->row_id);
        $this->assertEquals('prepayment', $prepaymentAllocations->first()->row_id);
    }

    public function test_scope_liability()
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

        // Create liability allocations
        PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-1',
            'row_id' => 'violation_guarantee',
            'description' => 'Violation Guarantee',
            'amount' => 500.00,
        ]);

        PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-2',
            'row_id' => 'prepayment',
            'description' => 'Prepayment',
            'amount' => 300.00,
        ]);

        // Create income allocation
        PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-3',
            'row_id' => 'rental_income',
            'description' => 'Rental Income',
            'amount' => 200.00,
        ]);

        $liabilityAllocations = PaymentReceiptAllocation::liability()->get();

        $this->assertCount(2, $liabilityAllocations);
        $this->assertTrue($liabilityAllocations->contains('row_id', 'violation_guarantee'));
        $this->assertTrue($liabilityAllocations->contains('row_id', 'prepayment'));
        $this->assertFalse($liabilityAllocations->contains('row_id', 'rental_income'));
    }

    public function test_scope_income()
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

        // Create liability allocation
        PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-1',
            'row_id' => 'violation_guarantee',
            'description' => 'Violation Guarantee',
            'amount' => 500.00,
        ]);

        // Create income allocations
        PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-2',
            'row_id' => 'rental_income',
            'description' => 'Rental Income',
            'amount' => 300.00,
        ]);

        PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-3',
            'row_id' => 'additional_fees',
            'description' => 'Additional Fees',
            'amount' => 200.00,
        ]);

        $incomeAllocations = PaymentReceiptAllocation::income()->get();

        $this->assertCount(2, $incomeAllocations);
        $this->assertTrue($incomeAllocations->contains('row_id', 'rental_income'));
        $this->assertTrue($incomeAllocations->contains('row_id', 'additional_fees'));
        $this->assertFalse($incomeAllocations->contains('row_id', 'violation_guarantee'));
    }

    public function test_casts_amount_to_decimal()
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

        $allocation = PaymentReceiptAllocation::create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => 'test-account-123',
            'row_id' => 'violation_guarantee',
            'description' => 'Violation Guarantee',
            'amount' => 500.50,
        ]);

        $this->assertIsFloat($allocation->amount);
        $this->assertEquals(500.50, $allocation->amount);
    }

    public function test_fillable_attributes()
    {
        $fillable = [
            'payment_receipt_id',
            'gl_account_id',
            'row_id',
            'description',
            'amount',
            'memo',
            'ifrs_line_item_id',
        ];

        $allocation = new PaymentReceiptAllocation();

        $this->assertEquals($fillable, $allocation->getFillable());
    }
}
