<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Team;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $team = Team::first();
        if (!$team) {
            $this->command->info('No team found. Please create a team first.');
            return;
        }

        $customers = Customer::where('team_id', $team->id)->take(3)->get();
        $vehicles = Vehicle::where('status', 'available')->take(3)->get();

        if ($customers->isEmpty() || $vehicles->isEmpty()) {
            $this->command->info('Not enough customers or vehicles found. Please create some first.');
            return;
        }

        $statuses = ['draft', 'active', 'completed'];
        
        for ($i = 0; $i < 5; $i++) {
            $customer = $customers->random();
            $vehicle = $vehicles->random();
            $status = $statuses[array_rand($statuses)];
            
            $startDate = now()->addDays(rand(1, 30));
            $endDate = $startDate->copy()->addDays(rand(1, 14));
            $totalDays = $startDate->diffInDays($endDate) + 1;
            $dailyRate = rand(100, 500);
            $totalAmount = $dailyRate * $totalDays;

            $contract = Contract::create([
                'contract_number' => Contract::generateContractNumber(),
                'team_id' => $team->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'daily_rate' => $dailyRate,
                'total_days' => $totalDays,
                'total_amount' => $totalAmount,
                'deposit_amount' => rand(0, 1000),
                'status' => $status,
                'created_by' => 'Seeder',
                'activated_at' => $status !== 'draft' ? now() : null,
                'completed_at' => $status === 'completed' ? now() : null,
                'notes' => 'Sample contract created by seeder',
            ]);

            $this->command->info("Created contract: {$contract->contract_number} ({$status})");
        }
    }
}
