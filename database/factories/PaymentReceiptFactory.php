<?php

namespace Database\Factories;

use App\Models\PaymentReceipt;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentReceipt>
 */
class PaymentReceiptFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PaymentReceipt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => 'PR-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 6, '0', STR_PAD_LEFT),
            'total_amount' => $this->faker->randomFloat(2, 100, 5000),
            'payment_method' => $this->faker->randomElement(['cash', 'card', 'bank_transfer']),
            'reference_number' => $this->faker->optional()->bothify('REF-####'),
            'payment_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['completed', 'pending', 'failed']),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => $this->faker->name(),
        ];
    }
}
