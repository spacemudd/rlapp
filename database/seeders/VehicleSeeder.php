<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = database_path('production_vehicles.csv');
        
        if (!File::exists($csvFile)) {
            $this->command->error("CSV file not found: {$csvFile}");
            return;
        }

        $csvData = array_map('str_getcsv', file($csvFile));
        $header = array_shift($csvData); // Remove header row
        
        $this->command->info("Processing " . count($csvData) . " vehicles from CSV...");
        
        foreach ($csvData as $row) {
            // Map CSV columns to array keys
            $vehicle = array_combine($header, $row);
            
            // Clean and convert the data
            $chassisNumber = $this->cleanString($vehicle['Chassis Number']);
            $uniqueChassisNumber = $this->makeChassisNumberUnique($chassisNumber);
            $makeName = $this->cleanString($vehicle['Car Name']);
            $modelName = $this->cleanString($vehicle['Model']);
            
            // Only create if required fields are present
            if (!empty($vehicle['Plate Number']) && 
                !empty($makeName) && 
                !empty($modelName) && 
                !empty($uniqueChassisNumber)) {
                
                try {
                    // Find or create vehicle make
                    $vehicleMake = VehicleMake::firstOrCreate(
                        ['name_en' => $makeName],
                        [
                            'name_ar' => $this->getMakeArabicTranslation($makeName),
                            'team_id' => null,
                        ]
                    );

                    // Find or create vehicle model
                    $vehicleModel = VehicleModel::firstOrCreate(
                        [
                            'vehicle_make_id' => $vehicleMake->id,
                            'name_en' => $modelName,
                        ],
                        [
                            'name_ar' => $this->getModelArabicTranslation($modelName),
                            'team_id' => null,
                        ]
                    );

                    $vehicleData = [
                        'plate_number' => $this->cleanString($vehicle['Plate Number']),
                        'make' => $makeName, // Keep legacy field for backward compatibility
                        'model' => $modelName, // Keep legacy field for backward compatibility
                        'vehicle_make_id' => $vehicleMake->id,
                        'vehicle_model_id' => $vehicleModel->id,
                        'year' => (int) $vehicle['Year'],
                        'color' => $this->cleanString($vehicle['Color']),
                        'category' => $this->cleanString($vehicle['Category']),
                        'price_daily' => $this->cleanPrice($vehicle['Price Daily']),
                        'price_weekly' => $this->cleanPrice($vehicle['Price Weekly']),
                        'price_monthly' => $this->cleanPrice($vehicle['Price Monthly']),
                        'status' => 'available',
                        'odometer' => 0,
                        'chassis_number' => $uniqueChassisNumber,
                        'license_expiry_date' => Carbon::now()->addYear(),
                        'insurance_expiry_date' => Carbon::now()->addYear(),
                        'recent_note' => 'Imported from CSV',
                    ];

                    Vehicle::create($vehicleData);
                    $this->command->info("Created vehicle: {$makeName} {$modelName} - {$vehicleData['plate_number']}");
                } catch (\Exception $e) {
                    $this->command->error("Failed to create vehicle {$vehicle['Plate Number']}: " . $e->getMessage());
                }
            } else {
                $this->command->warn("Skipping vehicle with missing required data: " . json_encode([
                    'plate_number' => $vehicle['Plate Number'] ?? '',
                    'make' => $makeName ?? '',
                    'model' => $modelName ?? '',
                    'chassis_number' => $uniqueChassisNumber ?? '',
                ]));
            }
        }
    }
    
    /**
     * Clean string data by removing extra whitespace and tabs
     */
    private function cleanString($value)
    {
        return trim(str_replace(["\t", "\n", "\r"], '', $value));
    }
    
    /**
     * Clean price data by removing "dh" prefix and converting to decimal
     */
    private function cleanPrice($value)
    {
        if (empty($value)) {
            return null;
        }
        
        // Remove "dh", commas, and extra whitespace
        $cleaned = preg_replace('/[^\d.]/', '', $value);
        
        return !empty($cleaned) ? (float) $cleaned : null;
    }
    
    /**
     * Make chassis number unique by appending duplicate suffix if needed
     */
    private function makeChassisNumberUnique($chassisNumber)
    {
        if (empty($chassisNumber)) {
            return $chassisNumber;
        }
        
        $originalChassisNumber = $chassisNumber;
        $counter = 1;
        
        // Check if chassis number already exists in database
        while (Vehicle::where('chassis_number', $chassisNumber)->exists()) {
            $chassisNumber = $originalChassisNumber . '-duplicate-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
            
            // Safety check to prevent infinite loop
            if ($counter > 99) {
                $chassisNumber = $originalChassisNumber . '-duplicate-' . uniqid();
                break;
            }
        }
        
        // Log if we had to modify the chassis number
        if ($chassisNumber !== $originalChassisNumber) {
            $this->command->warn("Duplicate chassis number detected. Changed '{$originalChassisNumber}' to '{$chassisNumber}'");
        }
        
        return $chassisNumber;
    }

    /**
     * Get Arabic translation for vehicle make
     */
    private function getMakeArabicTranslation($makeName)
    {
        $translations = [
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

        return $translations[$makeName] ?? $makeName;
    }

    /**
     * Get Arabic translation for vehicle model
     */
    private function getModelArabicTranslation($modelName)
    {
        $translations = [
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

        return $translations[$modelName] ?? $modelName;
    }
}
