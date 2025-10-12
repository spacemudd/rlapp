<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update existing branch quick_pay_accounts configurations
        // Move rental_income, vat_collection, and salik_fees from income to liability sections
        
        DB::table('branches')
            ->whereNotNull('quick_pay_accounts')
            ->chunkById(100, function ($branches) {
                foreach ($branches as $branch) {
                    $config = json_decode($branch->quick_pay_accounts, true);
                    
                    if (!$config || !is_array($config)) {
                        continue;
                    }
                    
                    $updated = false;
                    
                    // Ensure liability and income arrays exist
                    if (!isset($config['liability'])) {
                        $config['liability'] = [];
                    }
                    if (!isset($config['income'])) {
                        $config['income'] = [];
                    }
                    
                    // Items to move from income to liability
                    $itemsToMove = ['rental_income', 'vat_collection', 'salik_fees'];
                    
                    foreach ($itemsToMove as $item) {
                        // Check if item exists in income section
                        if (isset($config['income'][$item])) {
                            // Move to liability section
                            $config['liability'][$item] = $config['income'][$item];
                            // Remove from income section
                            unset($config['income'][$item]);
                            $updated = true;
                        }
                    }
                    
                    // Only update if changes were made
                    if ($updated) {
                        DB::table('branches')
                            ->where('id', $branch->id)
                            ->update([
                                'quick_pay_accounts' => json_encode($config),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Reverse the migration - move items back from liability to income
        DB::table('branches')
            ->whereNotNull('quick_pay_accounts')
            ->chunkById(100, function ($branches) {
                foreach ($branches as $branch) {
                    $config = json_decode($branch->quick_pay_accounts, true);
                    
                    if (!$config || !is_array($config)) {
                        continue;
                    }
                    
                    $updated = false;
                    
                    // Ensure liability and income arrays exist
                    if (!isset($config['liability'])) {
                        $config['liability'] = [];
                    }
                    if (!isset($config['income'])) {
                        $config['income'] = [];
                    }
                    
                    // Items to move back from liability to income
                    $itemsToMove = ['rental_income', 'vat_collection', 'salik_fees'];
                    
                    foreach ($itemsToMove as $item) {
                        // Check if item exists in liability section
                        if (isset($config['liability'][$item])) {
                            // Move back to income section
                            $config['income'][$item] = $config['liability'][$item];
                            // Remove from liability section
                            unset($config['liability'][$item]);
                            $updated = true;
                        }
                    }
                    
                    // Only update if changes were made
                    if ($updated) {
                        DB::table('branches')
                            ->where('id', $branch->id)
                            ->update([
                                'quick_pay_accounts' => json_encode($config),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
    }
};