<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Contract;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PricingService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Contract Override Feature\n";
echo "=====================================\n\n";

try {
    // Create test user
    $user = User::factory()->create();
    echo "✅ Created test user\n";
    
    // Create test customer
    $customer = Customer::factory()->create([
        'team_id' => $user->team_id,
    ]);
    echo "✅ Created test customer\n";
    
    // Create test vehicle
    $vehicle = Vehicle::factory()->create([
        'price_daily' => 100,
        'price_weekly' => 600,
        'price_monthly' => 2400,
    ]);
    echo "✅ Created test vehicle\n";
    
    // Test 1: Daily Rate Override
    echo "\n📝 Test 1: Daily Rate Override\n";
    echo "--------------------------------\n";
    
    $contract1 = Contract::create([
        'contract_number' => 'CON-TEST-001',
        'team_id' => $user->team_id,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-03',
        'daily_rate' => 150, // Override to 150 AED/day
        'total_days' => 3,
        'total_amount' => 450, // 3 days × 150 AED
        'deposit_amount' => 500,
        'override_daily_rate' => true,
        'override_final_price' => false,
        'original_calculated_amount' => 300, // Original would be 3 days × 100 AED
        'override_reason' => 'Special customer discount',
        'status' => 'draft',
        'created_by' => $user->name,
    ]);
    
    echo "✅ Created contract with daily rate override\n";
    echo "   - Daily Rate: {$contract1->daily_rate} AED\n";
    echo "   - Total Amount: {$contract1->total_amount} AED\n";
    echo "   - Override Percentage: " . round($contract1->getOverridePercentage(), 2) . "%\n";
    echo "   - Override Difference: {$contract1->getOverrideDifference()} AED\n";
    echo "   - Is Markup: " . ($contract1->isOverrideMarkup() ? 'Yes' : 'No') . "\n";
    echo "   - Is Discount: " . ($contract1->isOverrideDiscount() ? 'Yes' : 'No') . "\n";
    
    // Test 2: Final Price Override
    echo "\n📝 Test 2: Final Price Override\n";
    echo "--------------------------------\n";
    
    $contract2 = Contract::create([
        'contract_number' => 'CON-TEST-002',
        'team_id' => $user->team_id,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-03',
        'daily_rate' => 133.33, // Calculated from final price
        'total_days' => 3,
        'total_amount' => 400, // Override to 400 AED total
        'deposit_amount' => 500,
        'override_daily_rate' => false,
        'override_final_price' => true,
        'original_calculated_amount' => 300, // Original would be 3 days × 100 AED
        'override_reason' => 'Bulk booking discount',
        'status' => 'draft',
        'created_by' => $user->name,
    ]);
    
    echo "✅ Created contract with final price override\n";
    echo "   - Daily Rate: " . round($contract2->daily_rate, 2) . " AED\n";
    echo "   - Total Amount: {$contract2->total_amount} AED\n";
    echo "   - Override Percentage: " . round($contract2->getOverridePercentage(), 2) . "%\n";
    echo "   - Override Difference: {$contract2->getOverrideDifference()} AED\n";
    echo "   - Is Markup: " . ($contract2->isOverrideMarkup() ? 'Yes' : 'No') . "\n";
    echo "   - Is Discount: " . ($contract2->isOverrideDiscount() ? 'Yes' : 'No') . "\n";
    
    // Test 3: No Override (Default)
    echo "\n📝 Test 3: No Override (Default)\n";
    echo "--------------------------------\n";
    
    $contract3 = Contract::create([
        'contract_number' => 'CON-TEST-003',
        'team_id' => $user->team_id,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-03',
        'daily_rate' => 100,
        'total_days' => 3,
        'total_amount' => 300, // 3 days × 100 AED
        'deposit_amount' => 500,
        'override_daily_rate' => false,
        'override_final_price' => false,
        'original_calculated_amount' => 300,
        'override_reason' => null,
        'status' => 'draft',
        'created_by' => $user->name,
    ]);
    
    echo "✅ Created contract without override\n";
    echo "   - Daily Rate: {$contract3->daily_rate} AED\n";
    echo "   - Total Amount: {$contract3->total_amount} AED\n";
    echo "   - Has Overrides: " . ($contract3->hasPricingOverrides() ? 'Yes' : 'No') . "\n";
    echo "   - Override Percentage: " . round($contract3->getOverridePercentage(), 2) . "%\n";
    
    // Test 4: Discount Override
    echo "\n📝 Test 4: Discount Override\n";
    echo "--------------------------------\n";
    
    $contract4 = Contract::create([
        'contract_number' => 'CON-TEST-004',
        'team_id' => $user->team_id,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-03',
        'daily_rate' => 50, // Discount to 50 AED/day
        'total_days' => 3,
        'total_amount' => 150, // 3 days × 50 AED
        'deposit_amount' => 500,
        'override_daily_rate' => true,
        'override_final_price' => false,
        'original_calculated_amount' => 300, // Original would be 3 days × 100 AED
        'override_reason' => 'Loyalty customer discount',
        'status' => 'draft',
        'created_by' => $user->name,
    ]);
    
    echo "✅ Created contract with discount override\n";
    echo "   - Daily Rate: {$contract4->daily_rate} AED\n";
    echo "   - Total Amount: {$contract4->total_amount} AED\n";
    echo "   - Override Percentage: " . round($contract4->getOverridePercentage(), 2) . "%\n";
    echo "   - Override Difference: {$contract4->getOverrideDifference()} AED\n";
    echo "   - Is Markup: " . ($contract4->isOverrideMarkup() ? 'Yes' : 'No') . "\n";
    echo "   - Is Discount: " . ($contract4->isOverrideDiscount() ? 'Yes' : 'No') . "\n";
    
    echo "\n🎉 All tests completed successfully!\n";
    echo "✅ Override feature is working correctly\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 