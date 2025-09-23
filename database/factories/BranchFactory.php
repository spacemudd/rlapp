<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'address' => $this->faker->address,
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'description' => $this->faker->optional()->sentence,
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'ifrs_vat_account_id' => null,
            'quick_pay_accounts' => null,
        ];
    }

    /**
     * Indicate that the branch is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the branch is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Add quick pay accounts configuration.
     */
    public function withQuickPayAccounts(): static
    {
        return $this->state(fn (array $attributes) => [
            'quick_pay_accounts' => [
                'liability' => [
                    'violation_guarantee' => $this->faker->uuid,
                    'prepayment' => $this->faker->uuid,
                ],
                'income' => [
                    'rental_income' => $this->faker->uuid,
                    'vat_collection' => $this->faker->uuid,
                    'insurance_fee' => $this->faker->uuid,
                    'fines' => $this->faker->uuid,
                    'salik_fees' => $this->faker->uuid,
                ],
            ],
        ]);
    }
}
