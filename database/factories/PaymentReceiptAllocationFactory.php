<?php

namespace Database\Factories;

use App\Models\PaymentReceiptAllocation;
use App\Models\PaymentReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentReceiptAllocation>
 */
class PaymentReceiptAllocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PaymentReceiptAllocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'row_id' => $this->faker->randomElement(['rental_income', 'additional_fees', 'prepayment', 'violation_guarantee']),
            'description' => $this->faker->sentence(3),
            'amount' => $this->faker->randomFloat(2, 50, 1000),
            'memo' => $this->faker->optional()->sentence(),
            'gl_account_id' => 1, // Default GL account ID for testing
        ];
    }
}
