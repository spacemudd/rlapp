<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get existing data
        $customers = Customer::all();
        $vehicles = Vehicle::all();
        $team = Team::first();

        if ($customers->isEmpty() || $vehicles->isEmpty()) {
            echo "Please seed customers and vehicles first.\n";
            return;
        }

        // Create 10 contracts
        for ($i = 1; $i <= 10; $i++) {
            $startDate = Carbon::now()->addDays(rand(-30, 30))->startOfDay();
            $endDate = $startDate->copy()->addDays(rand(1, 30));
            $dailyRate = rand(100, 500);
            $totalDays = $startDate->diffInDays($endDate);
            $totalAmount = $dailyRate * $totalDays;

            Contract::create([
                'id' => Str::uuid(),
                'contract_number' => 'CNT-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'status' => rand(0, 1) ? 'active' : 'completed',
                'customer_id' => $customers->random()->id,
                'vehicle_id' => $vehicles->random()->id,
                'team_id' => $team->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'signed_at' => $startDate->subHours(rand(1, 24)),
                'activated_at' => $startDate,
                'completed_at' => rand(0, 1) ? $endDate : null,
                'total_amount' => $totalAmount,
                'deposit_amount' => $totalAmount * 0.2, // 20% deposit
                'daily_rate' => $dailyRate,
                'total_days' => $totalDays,
                'currency' => 'AED',
                'mileage_limit' => rand(100, 300),
                'excess_mileage_rate' => 2.00,
                'terms_and_conditions' => 'Standard rental terms and conditions apply.',
                'notes' => 'Contract created by seeder',
                'created_by' => 'Seeder',
                'approved_by' => 'System',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
