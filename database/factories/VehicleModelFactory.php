<?php

namespace Database\Factories;

use App\Models\VehicleModel;
use App\Models\VehicleMake;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleModel>
 */
class VehicleModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VehicleModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $models = [
            // BMW Models
            ['make' => 'BMW', 'en' => 'X5', 'ar' => 'اكس 5'],
            ['make' => 'BMW', 'en' => 'X3', 'ar' => 'اكس 3'],
            ['make' => 'BMW', 'en' => 'X7', 'ar' => 'اكس 7'],
            ['make' => 'BMW', 'en' => '3 Series', 'ar' => 'سيريز 3'],
            ['make' => 'BMW', 'en' => '5 Series', 'ar' => 'سيريز 5'],
            ['make' => 'BMW', 'en' => '7 Series', 'ar' => 'سيريز 7'],
            ['make' => 'BMW', 'en' => 'i8', 'ar' => 'آي 8'],
            ['make' => 'BMW', 'en' => 'iX', 'ar' => 'آي اكس'],
            
            // Mercedes-Benz Models
            ['make' => 'Mercedes-Benz', 'en' => 'C-Class', 'ar' => 'فئة سي'],
            ['make' => 'Mercedes-Benz', 'en' => 'E-Class', 'ar' => 'فئة إي'],
            ['make' => 'Mercedes-Benz', 'en' => 'S-Class', 'ar' => 'فئة إس'],
            ['make' => 'Mercedes-Benz', 'en' => 'GLE', 'ar' => 'جي إل إي'],
            ['make' => 'Mercedes-Benz', 'en' => 'GLS', 'ar' => 'جي إل إس'],
            ['make' => 'Mercedes-Benz', 'en' => 'AMG GT', 'ar' => 'إيه إم جي جي تي'],
            ['make' => 'Mercedes-Benz', 'en' => 'A-Class', 'ar' => 'فئة إيه'],
            ['make' => 'Mercedes-Benz', 'en' => 'CLA', 'ar' => 'سي إل إيه'],
            
            // Audi Models
            ['make' => 'Audi', 'en' => 'A4', 'ar' => 'إيه 4'],
            ['make' => 'Audi', 'en' => 'A6', 'ar' => 'إيه 6'],
            ['make' => 'Audi', 'en' => 'A8', 'ar' => 'إيه 8'],
            ['make' => 'Audi', 'en' => 'Q5', 'ar' => 'كيو 5'],
            ['make' => 'Audi', 'en' => 'Q7', 'ar' => 'كيو 7'],
            ['make' => 'Audi', 'en' => 'Q8', 'ar' => 'كيو 8'],
            ['make' => 'Audi', 'en' => 'R8', 'ar' => 'آر 8'],
            ['make' => 'Audi', 'en' => 'TT', 'ar' => 'تي تي'],
            
            // Toyota Models
            ['make' => 'Toyota', 'en' => 'Camry', 'ar' => 'كامري'],
            ['make' => 'Toyota', 'en' => 'Corolla', 'ar' => 'كورولا'],
            ['make' => 'Toyota', 'en' => 'RAV4', 'ar' => 'راف 4'],
            ['make' => 'Toyota', 'en' => 'Highlander', 'ar' => 'هايلاندر'],
            ['make' => 'Toyota', 'en' => 'Land Cruiser', 'ar' => 'لاند كروزر'],
            ['make' => 'Toyota', 'en' => 'Prius', 'ar' => 'بريوس'],
            ['make' => 'Toyota', 'en' => '4Runner', 'ar' => '4 رنر'],
            ['make' => 'Toyota', 'en' => 'Sequoia', 'ar' => 'سيكويا'],
        ];

        $model = $this->faker->randomElement($models);

        return [
            'vehicle_make_id' => VehicleMake::factory()->state(['name_en' => $model['make']]),
            'name_en' => $model['en'],
            'name_ar' => $model['ar'],
            'team_id' => Team::factory(),
        ];
    }

    /**
     * Create a BMW X5 model.
     */
    public function bmwX5(): static
    {
        return $this->state(fn (array $attributes) => [
            'vehicle_make_id' => VehicleMake::factory()->bmw(),
            'name_en' => 'X5',
            'name_ar' => 'اكس 5',
        ]);
    }

    /**
     * Create a Mercedes C-Class model.
     */
    public function mercedesCClass(): static
    {
        return $this->state(fn (array $attributes) => [
            'vehicle_make_id' => VehicleMake::factory()->mercedes(),
            'name_en' => 'C-Class',
            'name_ar' => 'فئة سي',
        ]);
    }

    /**
     * Create an Audi A4 model.
     */
    public function audiA4(): static
    {
        return $this->state(fn (array $attributes) => [
            'vehicle_make_id' => VehicleMake::factory()->audi(),
            'name_en' => 'A4',
            'name_ar' => 'إيه 4',
        ]);
    }

    /**
     * Create a Toyota Camry model.
     */
    public function toyotaCamry(): static
    {
        return $this->state(fn (array $attributes) => [
            'vehicle_make_id' => VehicleMake::factory()->toyota(),
            'name_en' => 'Camry',
            'name_ar' => 'كامري',
        ]);
    }
}
