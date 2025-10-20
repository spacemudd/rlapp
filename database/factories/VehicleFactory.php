<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $makes = ['Toyota', 'Honda', 'Nissan', 'BMW', 'Mercedes', 'Audi', 'Ford', 'Chevrolet', 'Hyundai', 'Kia'];
        $models = ['Camry', 'Civic', 'Altima', '3 Series', 'C-Class', 'A4', 'Focus', 'Malibu', 'Elantra', 'Forte'];
        $categories = ['Economy', 'Compact', 'Mid-size', 'Full-size', 'SUV', 'Luxury', 'Sports'];
        
        return [
            'plate_number' => $this->faker->regexify('[A-Z]{1,2}-[0-9]{5}'),
            'make' => $this->faker->randomElement($makes),
            'model' => $this->faker->randomElement($models),
            'year' => $this->faker->numberBetween(2018, 2024),
            'color' => $this->faker->colorName(),
            'category' => $this->faker->randomElement($categories),
            'chassis_number' => $this->faker->regexify('[A-Z0-9]{17}'),
            'status' => 'available',
            'price_daily' => $this->faker->numberBetween(100, 500),
            'price_weekly' => $this->faker->numberBetween(600, 3000),
            'price_monthly' => $this->faker->numberBetween(2400, 12000),
            'price_yearly' => $this->faker->numberBetween(28800, 144000),
            'odometer' => $this->faker->numberBetween(10000, 100000),
            'license_expiry_date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'insurance_expiry_date' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'seats' => $this->faker->numberBetween(2, 8),
        ];
    }
}
