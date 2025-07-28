<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'date_of_birth' => $this->faker->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'),
            'drivers_license_number' => $this->faker->regexify('[A-Z]{2}[0-9]{6}'),
            'drivers_license_expiry' => $this->faker->dateTimeBetween('now', '+5 years')->format('Y-m-d'),
            'country' => 'United Arab Emirates',
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->phoneNumber(),
            'status' => 'active',
            'notes' => $this->faker->optional()->sentence(),
            'team_id' => null, // Will be set when creating
        ];
    }
}
