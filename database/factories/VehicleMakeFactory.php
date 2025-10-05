<?php

namespace Database\Factories;

use App\Models\VehicleMake;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleMake>
 */
class VehicleMakeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VehicleMake::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $makes = [
            ['en' => 'BMW', 'ar' => 'بي ام دبليو'],
            ['en' => 'Mercedes-Benz', 'ar' => 'مرسيدس بنز'],
            ['en' => 'Audi', 'ar' => 'أودي'],
            ['en' => 'Toyota', 'ar' => 'تويوتا'],
            ['en' => 'Honda', 'ar' => 'هوندا'],
            ['en' => 'Nissan', 'ar' => 'نيسان'],
            ['en' => 'Ford', 'ar' => 'فورد'],
            ['en' => 'Chevrolet', 'ar' => 'شيفروليه'],
            ['en' => 'Hyundai', 'ar' => 'هيونداي'],
            ['en' => 'Kia', 'ar' => 'كيا'],
            ['en' => 'Lexus', 'ar' => 'لكزس'],
            ['en' => 'Infiniti', 'ar' => 'إنفينيتي'],
            ['en' => 'Mazda', 'ar' => 'مازدا'],
            ['en' => 'Subaru', 'ar' => 'سوبارو'],
            ['en' => 'Volkswagen', 'ar' => 'فولكس فاجن'],
            ['en' => 'Porsche', 'ar' => 'بورش'],
            ['en' => 'Jaguar', 'ar' => 'جاغوار'],
            ['en' => 'Land Rover', 'ar' => 'لاند روفر'],
            ['en' => 'Range Rover', 'ar' => 'رينج روفر'],
            ['en' => 'Maserati', 'ar' => 'مازيراتي'],
            ['en' => 'Ferrari', 'ar' => 'فيراري'],
            ['en' => 'Lamborghini', 'ar' => 'لامبورغيني'],
            ['en' => 'Bentley', 'ar' => 'بنتلي'],
            ['en' => 'Rolls-Royce', 'ar' => 'رولز رويس'],
            ['en' => 'McLaren', 'ar' => 'ماكلارين'],
            ['en' => 'Aston Martin', 'ar' => 'أستون مارتن'],
            ['en' => 'Bugatti', 'ar' => 'بوجاتي'],
            ['en' => 'Koenigsegg', 'ar' => 'كونيغسيغ'],
            ['en' => 'Pagani', 'ar' => 'باغاني'],
            ['en' => 'Rimac', 'ar' => 'ريماك'],
        ];

        $make = $this->faker->randomElement($makes);

        return [
            'name_en' => $make['en'],
            'name_ar' => $make['ar'],
            'team_id' => Team::factory(),
        ];
    }

    /**
     * Create a BMW make.
     */
    public function bmw(): static
    {
        return $this->state(fn (array $attributes) => [
            'name_en' => 'BMW',
            'name_ar' => 'بي ام دبليو',
        ]);
    }

    /**
     * Create a Mercedes-Benz make.
     */
    public function mercedes(): static
    {
        return $this->state(fn (array $attributes) => [
            'name_en' => 'Mercedes-Benz',
            'name_ar' => 'مرسيدس بنز',
        ]);
    }

    /**
     * Create an Audi make.
     */
    public function audi(): static
    {
        return $this->state(fn (array $attributes) => [
            'name_en' => 'Audi',
            'name_ar' => 'أودي',
        ]);
    }

    /**
     * Create a Toyota make.
     */
    public function toyota(): static
    {
        return $this->state(fn (array $attributes) => [
            'name_en' => 'Toyota',
            'name_ar' => 'تويوتا',
        ]);
    }
}
