<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+30 days');
        $endDate = Carbon::parse($startDate)->addDays($this->faker->numberBetween(1, 14));
        $dailyRate = $this->faker->randomFloat(2, 200, 800);
        $totalDays = Carbon::parse($startDate)->diffInDays($endDate);

        return [
            'contract_number' => 'CT-' . $this->faker->unique()->numberBetween(100000, 999999),
            'status' => $this->faker->randomElement(['draft', 'active', 'completed', 'void']),
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'team_id' => Team::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'signed_at' => $this->faker->optional(0.8)->dateTimeBetween($startDate, $endDate),
            'activated_at' => $this->faker->optional(0.7)->dateTimeBetween($startDate, $endDate),
            'completed_at' => $this->faker->optional(0.3)->dateTimeBetween($startDate, $endDate),
            'voided_at' => $this->faker->optional(0.1)->dateTimeBetween($startDate, $endDate),
            'total_amount' => $dailyRate * $totalDays,
            'deposit_amount' => $this->faker->randomFloat(2, 500, 2000),
            'deposit_type' => $this->faker->randomElement(['refundable', 'non_refundable']),
            'deposit_received_at' => $this->faker->optional(0.9)->dateTimeBetween($startDate, $endDate),
            'deposit_payment_method' => $this->faker->randomElement(['cash', 'card', 'bank_transfer']),
            'daily_rate' => $dailyRate,
            'total_days' => $totalDays,
            'currency' => 'AED',
            'mileage_limit' => $this->faker->numberBetween(100, 500),
            'excess_mileage_rate' => $this->faker->randomFloat(2, 0.5, 2.0),
            'terms_and_conditions' => $this->faker->optional(0.8)->paragraphs(3, true),
            'notes' => $this->faker->optional(0.6)->sentence(),
            'created_by' => $this->faker->name(),
            'approved_by' => $this->faker->optional(0.8)->name(),
            'void_reason' => $this->faker->optional(0.1)->sentence(),
            'pickup_mileage' => $this->faker->numberBetween(1000, 50000),
            'pickup_fuel_level' => $this->faker->randomElement(['empty', 'low', '1/4', '1/2', '3/4', 'full']),
            'pickup_condition_photos' => $this->faker->optional(0.7)->randomElements(['photo1.jpg', 'photo2.jpg', 'photo3.jpg'], $this->faker->numberBetween(1, 3)),
            'return_mileage' => $this->faker->optional(0.5)->numberBetween(1000, 50000),
            'return_fuel_level' => $this->faker->optional(0.5)->randomElement(['empty', 'low', '1/4', '1/2', '3/4', 'full']),
            'return_condition_photos' => $this->faker->optional(0.3)->randomElements(['photo1.jpg', 'photo2.jpg', 'photo3.jpg'], $this->faker->numberBetween(1, 3)),
            'excess_mileage_charge' => $this->faker->optional(0.3)->randomFloat(2, 0, 500),
            'fuel_charge' => $this->faker->optional(0.3)->randomFloat(2, 0, 200),
            'override_daily_rate' => $this->faker->boolean(20),
            'override_final_price' => $this->faker->boolean(15),
            'original_calculated_amount' => $this->faker->optional(0.8)->randomFloat(2, 1000, 10000),
            'override_reason' => $this->faker->optional(0.2)->sentence(),
        ];
    }

    /**
     * Indicate that the contract is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    /**
     * Indicate that the contract is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the contract is void.
     */
    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'void',
            'voided_at' => now(),
            'void_reason' => $this->faker->sentence(),
        ]);
    }
}
