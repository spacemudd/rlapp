<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pickupDate = $this->faker->dateTimeBetween('now', '+30 days');
        $returnDate = Carbon::parse($pickupDate)->addDays($this->faker->numberBetween(1, 14));
        $rate = $this->faker->randomFloat(2, 200, 800);
        $durationDays = Carbon::parse($pickupDate)->diffInDays($returnDate);

        return [
            'uid' => 'RES-' . strtoupper($this->faker->unique()->bothify('########')),
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'team_id' => Team::factory(),
            'rate' => $rate,
            'pickup_date' => $pickupDate,
            'pickup_location' => $this->faker->address(),
            'return_date' => $returnDate,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'completed', 'canceled', 'expired']),
            'reservation_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'notes' => $this->faker->optional(0.6)->sentence(),
            'total_amount' => $rate * $durationDays,
            'duration_days' => $durationDays,
        ];
    }

    /**
     * Indicate that the reservation is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the reservation is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Indicate that the reservation is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the reservation is canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
        ]);
    }

    /**
     * Indicate that the reservation is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
        ]);
    }
}
