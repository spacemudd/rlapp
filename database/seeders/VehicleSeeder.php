<?php

namespace Database\Seeders;

use App\Models\Vehicle;
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
            
            $vehicleData = [
                'plate_number' => $this->cleanString($vehicle['Plate Number']),
                'make' => $this->cleanString($vehicle['Car Name']),
                'model' => $this->cleanString($vehicle['Model']),
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
            
            // Only create if required fields are present
            if (!empty($vehicleData['plate_number']) && 
                !empty($vehicleData['make']) && 
                !empty($vehicleData['model']) && 
                !empty($vehicleData['chassis_number'])) {
                
                try {
                    Vehicle::create($vehicleData);
                    $this->command->info("Created vehicle: {$vehicleData['make']} {$vehicleData['model']} - {$vehicleData['plate_number']}");
                } catch (\Exception $e) {
                    $this->command->error("Failed to create vehicle {$vehicleData['plate_number']}: " . $e->getMessage());
                }
            } else {
                $this->command->warn("Skipping vehicle with missing required data: " . json_encode($vehicleData));
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
}
