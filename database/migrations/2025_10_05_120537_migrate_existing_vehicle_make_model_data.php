<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\Vehicle;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Arabic translations for vehicle makes
        $makeTranslations = [
            'Hyundai' => 'هيونداي',
            'Chevrolet' => 'شيفروليه',
            'Nissan' => 'نيسان',
            'Infiniti' => 'انفينيتي',
            'Toyota' => 'تويوتا',
            'Mitsubishi' => 'ميتسوبيشي',
            'BMW' => 'بي ام دبليو',
            'Kia' => 'كيا',
            'Honda' => 'هوندا',
            'Mercedes Benz' => 'مرسيدس بنز',
            'Land Rover' => 'لاند روفر',
            'Cadillac' => 'كاديلاك',
            'Ford' => 'فورد',
            'Jetour' => 'جيتور',
            'Range Rover' => 'رينج روفر',
        ];

        // Arabic translations for vehicle models
        $modelTranslations = [
            'Elantra' => 'النترا',
            'Malibu' => 'ماليبو',
            'Altima' => 'التيما',
            'QX50' => 'كيو اكس 50',
            'Hiace' => 'هايس',
            'Outlander' => 'اوتلاندر',
            'Versa' => 'فيرسا',
            '228i' => '228 اي',
            'Santa Fe' => 'سانتا في',
            'Sonata' => 'سوناتا',
            'Cerato' => 'سيراتو',
            'Venue' => 'فينيو',
            'Odyssey' => 'اوديسي',
            'Sunny' => 'صني',
            'CLA 250' => 'سي ال ايه 250',
            'Defender' => 'ديفندر',
            'S500' => 'اس 500',
            'Range Rover Vogue' => 'رينج روفر فوغ',
            'GLS 450' => 'جي ال اس 450',
            'GLE 53' => 'جي ال اي 53',
            'Tucson' => 'توسان',
            'Escalade' => 'اسكاليد',
            '3 Series' => 'سيريز 3',
            '5 Series' => 'سيريز 5',
            'Corvette' => 'كورفيت',
            'GLC 300' => 'جي ال سي 300',
            'Velar' => 'فيلار',
            'Mustang' => 'مستنج',
            '740i' => '740 اي',
            'E350' => 'اي 350',
            'QX60' => 'كيو اكس 60',
            'Rogue' => 'روج',
            'Sentra' => 'سنترا',
            'Sportage' => 'سبورتاج',
            'Carnival' => 'كارنيفال',
            'Forte' => 'فورتي',
            'Kona' => 'كونا',
            'T2' => 'تي 2',
            'K5' => 'كيه 5',
            'Defender V4' => 'ديفندر في 4',
            'Defender V6' => 'ديفندر في 6',
            'Range Rover Vouge V6' => 'رينج روفر فوغ في 6',
            'Range Rover Vouge' => 'رينج روفر فوغ',
            '3 Series 330i' => 'سيريز 3 330 اي',
            '3 Series 330 Xdrive' => 'سيريز 3 330 اكس درايف',
            '5 Series 530i' => 'سيريز 5 530 اي',
            'Corvette Stingray Couple 1LT' => 'كورفيت ستينغراي كوبل 1 ال تي',
            'GLC 300 4Matic' => 'جي ال سي 300 4 ماتيك',
            'CLA 250 4Matic AMG' => 'سي ال ايه 250 4 ماتيك ايه ام جي',
            'Velar R-Dynamic S' => 'فيلار ار ديناميك اس',
        ];

        // Get unique make/model combinations from existing vehicles
        $existingVehicles = Vehicle::select('make', 'model')
            ->whereNotNull('make')
            ->whereNotNull('model')
            ->distinct()
            ->get();

        // Get unique make/model combinations from CSV data
        $csvFile = database_path('production_vehicles.csv');
        $csvMakes = [];
        $csvModels = [];
        
        if (file_exists($csvFile)) {
            $csvData = array_map('str_getcsv', file($csvFile));
            $header = array_shift($csvData);
            
            foreach ($csvData as $row) {
                $vehicle = array_combine($header, $row);
                $make = trim($vehicle['Car Name'] ?? '');
                $model = trim($vehicle['Model'] ?? '');
                
                if (!empty($make)) {
                    $csvMakes[$make] = true;
                }
                if (!empty($model)) {
                    $csvModels[$make][$model] = true;
                }
            }
        }

        // Combine all makes
        $allMakes = [];
        foreach ($existingVehicles as $vehicle) {
            if (!empty($vehicle->make)) {
                $allMakes[$vehicle->make] = true;
            }
        }
        $allMakes = array_merge($allMakes, $csvMakes);

        // Create vehicle makes
        $makeIds = [];
        foreach (array_keys($allMakes) as $makeName) {
            $make = VehicleMake::firstOrCreate(
                ['name_en' => $makeName],
                [
                    'name_ar' => $makeTranslations[$makeName] ?? $makeName,
                    'team_id' => null,
                ]
            );
            $makeIds[$makeName] = $make->id;
        }

        // Combine all models
        $allModels = [];
        foreach ($existingVehicles as $vehicle) {
            if (!empty($vehicle->make) && !empty($vehicle->model)) {
                $allModels[$vehicle->make][$vehicle->model] = true;
            }
        }
        $allModels = array_merge_recursive($allModels, $csvModels);

        // Create vehicle models
        $modelIds = [];
        foreach ($allModels as $makeName => $models) {
            if (!isset($makeIds[$makeName])) {
                continue;
            }
            
            foreach (array_keys($models) as $modelName) {
                $model = VehicleModel::firstOrCreate(
                    [
                        'vehicle_make_id' => $makeIds[$makeName],
                        'name_en' => $modelName,
                    ],
                    [
                        'name_ar' => $modelTranslations[$modelName] ?? $modelName,
                        'team_id' => null,
                    ]
                );
                $modelIds[$makeName][$modelName] = $model->id;
            }
        }

        // Update existing vehicles with foreign keys
        foreach ($existingVehicles as $vehicle) {
            if (!empty($vehicle->make) && !empty($vehicle->model)) {
                $makeId = $makeIds[$vehicle->make] ?? null;
                $modelId = $modelIds[$vehicle->make][$vehicle->model] ?? null;
                
                if ($makeId && $modelId) {
                    Vehicle::where('make', $vehicle->make)
                        ->where('model', $vehicle->model)
                        ->update([
                            'vehicle_make_id' => $makeId,
                            'vehicle_model_id' => $modelId,
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear foreign key references
        Vehicle::whereNotNull('vehicle_make_id')->update(['vehicle_make_id' => null]);
        Vehicle::whereNotNull('vehicle_model_id')->update(['vehicle_model_id' => null]);
        
        // Delete vehicle models
        VehicleModel::truncate();
        
        // Delete vehicle makes
        VehicleMake::truncate();
    }
};
