<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptAllocation;
use App\Models\Vehicle;
use App\Services\AccountingService;
use IFRS\Models\Account;
use IFRS\Models\Transaction;
use IFRS\Models\LineItem;
use IFRS\Models\Entity;
use IFRS\Models\Currency;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentReceiptDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountingService = new AccountingService();
    }

    public function test_payment_receipt_has_required_details()
    {
        // Setup test data
        $customer = Customer::factory()->create([
            'first_name' => 'MESHARI',
            'last_name' => 'SALEM',
            'email' => 'meshari@example.com',
            'phone' => '1234567890',
            'drivers_license_number' => 'DL123456789',
            'drivers_license_expiry' => now()->addYears(5)->toDateString(),
            'date_of_birth' => '1990-01-01',
            'city' => 'Riyadh',
            'country' => 'Saudi Arabia',
            'nationality' => 'Saudi',
            'status' => 'active',
        ]);

        $branch = Branch::factory()->create([
            'name' => 'Main Branch',
        ]);

        $vehicle = Vehicle::factory()->create([
            'branch_id' => $branch->id,
            'make' => 'BMW',
            'model' => '3 Series',
            'year' => 2023,
            'plate_number' => 'A47571',
            'color' => 'White',
            'category' => 'sedan',
            'odometer' => 10000,
            'chassis_number' => 'CHASSIS123456789',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'insurance_expiry_date' => now()->addYears(1)->toDateString(),
        ]);

        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'branch_id' => $branch->id,
            'contract_number' => 'CON-21573-1175',
            'total_days' => 5,
            'daily_rate' => 300.00,
            'total_amount' => 1500.00,
        ]);

        $paymentReceipt = PaymentReceipt::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'receipt_number' => 'PR-21573-1175',
            'total_amount' => 150.00,
            'payment_method' => 'cash',
            'reference_number' => 'DEPOSIT_ALLOWANCE',
            'payment_date' => '2025-09-21',
            'status' => 'completed',
            'created_by' => 'System Admin',
        ]);

        // Test that payment receipt has all required details
        $this->assertNotNull($paymentReceipt->contract);
        $this->assertNotNull($paymentReceipt->customer);
        $this->assertNotNull($paymentReceipt->branch);

        // Test contract details
        $this->assertEquals('CON-21573-1175', $paymentReceipt->contract->contract_number);
        $this->assertEquals('MESHARI SALEM', $paymentReceipt->customer->first_name . ' ' . $paymentReceipt->customer->last_name);
        $this->assertEquals('BMW 3 Series', $paymentReceipt->contract->vehicle->make . ' ' . $paymentReceipt->contract->vehicle->model);
        $this->assertEquals('A47571', $paymentReceipt->contract->vehicle->plate_number);

        // Test receipt details
        $this->assertEquals('PR-21573-1175', $paymentReceipt->receipt_number);
        $this->assertEquals(150.00, $paymentReceipt->total_amount);
        $this->assertEquals('cash', $paymentReceipt->payment_method);
        $this->assertEquals('DEPOSIT_ALLOWANCE', $paymentReceipt->reference_number);
        $this->assertEquals('completed', $paymentReceipt->status);
        $this->assertEquals('System Admin', $paymentReceipt->created_by);
    }

    public function test_payment_receipt_details_formatting()
    {
        $customer = Customer::factory()->create([
            'first_name' => 'AHMED',
            'last_name' => 'HASSAN',
        ]);

        $branch = Branch::factory()->create([
            'name' => 'Test Branch',
        ]);

        $vehicle = Vehicle::factory()->create([
            'make' => 'Toyota',
            'model' => 'Camry',
            'plate_number' => 'B12345',
            'branch_id' => $branch->id,
        ]);

        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'branch_id' => $branch->id,
            'contract_number' => 'CON-12345',
        ]);

        $paymentReceipt = PaymentReceipt::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'receipt_number' => 'PR-12345',
            'total_amount' => 500.00,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK_REF_123',
            'payment_date' => '2025-09-22',
            'status' => 'completed',
            'created_by' => 'John Doe',
        ]);

        // Test formatted details
        $customerName = $customer->first_name . ' ' . $customer->last_name;
        $expectedDescription = "Order #{$contract->contract_number}, Customer: {$customerName}, Vehicle: #{$vehicle->plate_number} ({$vehicle->make} {$vehicle->model})";
        
        $this->assertStringContainsString($contract->contract_number, $expectedDescription);
        $this->assertStringContainsString($customerName, $expectedDescription);
        $this->assertStringContainsString($vehicle->plate_number, $expectedDescription);
        $this->assertStringContainsString($vehicle->make, $expectedDescription);
        $this->assertStringContainsString($vehicle->model, $expectedDescription);
    }

    public function test_payment_receipt_with_multiple_allocations()
    {
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();
        $vehicle = Vehicle::factory()->create(['branch_id' => $branch->id]);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'branch_id' => $branch->id,
        ]);

        $paymentReceipt = PaymentReceipt::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 1000.00,
        ]);

        // Create a test GL account for allocations
        $entity = \IFRS\Models\Entity::create([
            'name' => 'Test Entity',
            'multi_currency' => false,
            'functional_currency_id' => 1,
        ]);
        
        $currency = \IFRS\Models\Currency::create([
            'name' => 'UAE Dirham',
            'currency_code' => 'AED',
            'entity_id' => $entity->id,
        ]);
        
        $glAccount = \IFRS\Models\Account::create([
            'name' => 'Test GL Account',
            'account_type' => \IFRS\Models\Account::OPERATING_REVENUE,
            'entity_id' => $entity->id,
            'currency_id' => $currency->id,
        ]);
        
        $glAccountId = $glAccount->id;

        // Create multiple allocations
        PaymentReceiptAllocation::factory()->create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => $glAccountId,
            'row_id' => 'rental_income',
            'description' => 'Rental Income',
            'amount' => 600.00,
            'memo' => 'Monthly rental payment',
        ]);

        PaymentReceiptAllocation::factory()->create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => $glAccountId,
            'row_id' => 'additional_fees',
            'description' => 'Additional Fees',
            'amount' => 200.00,
            'memo' => 'Late payment fee',
        ]);

        PaymentReceiptAllocation::factory()->create([
            'payment_receipt_id' => $paymentReceipt->id,
            'gl_account_id' => $glAccountId,
            'row_id' => 'prepayment',
            'description' => 'Prepayment',
            'amount' => 200.00,
            'memo' => 'Advance payment',
        ]);

        $paymentReceipt->load('allocations');

        $this->assertCount(3, $paymentReceipt->allocations);
        $this->assertEquals(1000.00, $paymentReceipt->allocations->sum('amount'));
    }

    public function test_payment_receipt_status_translations()
    {
        $statuses = ['completed', 'pending', 'failed'];
        $expectedTranslations = [
            'completed' => ['en' => 'Completed', 'ar' => 'مكتمل'],
            'pending' => ['en' => 'Pending', 'ar' => 'معلق'],
            'failed' => ['en' => 'Failed', 'ar' => 'فشل'],
        ];

        foreach ($statuses as $status) {
            $this->assertArrayHasKey($status, $expectedTranslations);
            $this->assertArrayHasKey('en', $expectedTranslations[$status]);
            $this->assertArrayHasKey('ar', $expectedTranslations[$status]);
        }
    }

    public function test_payment_receipt_method_translations()
    {
        $methods = ['cash', 'card', 'bank_transfer'];
        $expectedTranslations = [
            'cash' => ['en' => 'Cash', 'ar' => 'نقداً'],
            'card' => ['en' => 'Card', 'ar' => 'بطاقة'],
            'bank_transfer' => ['en' => 'Bank Transfer', 'ar' => 'تحويل بنكي'],
        ];

        foreach ($methods as $method) {
            $this->assertArrayHasKey($method, $expectedTranslations);
            $this->assertArrayHasKey('en', $expectedTranslations[$method]);
            $this->assertArrayHasKey('ar', $expectedTranslations[$method]);
        }
    }
}
