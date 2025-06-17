<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class CustomersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::where('name', 'Luxuria Cars LLC')->first();
        
        if (!$team) {
            $this->command->warn('Team "Luxuria Cars LLC" not found. Please run TeamsSeeder first.');
            return;
        }

        // Ask how many customers to create (default 100)
        $count = $this->command->ask('How many customers would you like to create?', 100);
        $count = (int) $count;

        $faker = Faker::create();
        
        // UAE-specific data arrays
        $uaeCities = ['Dubai', 'Abu Dhabi', 'Sharjah', 'Al Ain', 'Ajman', 'Ras Al Khaimah', 'Fujairah'];
        
        $dubaiAreas = [
            'Dubai Marina', 'Downtown Dubai', 'Jumeirah Beach Residence', 'Business Bay',
            'Al Barsha', 'Jumeirah', 'Deira', 'Bur Dubai', 'Al Wasl', 'Emirates Hills',
            'Dubai Sports City', 'The Greens', 'International City', 'Al Nahda',
            'Motor City', 'Arabian Ranches', 'The Springs', 'The Meadows'
        ];

        $abuDhabiAreas = [
            'Corniche', 'Al Reem Island', 'Yas Island', 'Saadiyat Island',
            'Al Khalidiyah', 'Al Mariah Island', 'Al Raha Beach', 'Masdar City'
        ];

        $commonLastNames = [
            // Arabic/Emirati surnames
            'Al Mansouri', 'Al Zaabi', 'Al Maktoum', 'Al Rashid', 'Al Nuaimi', 'Al Dhaheri',
            'Al Shamsi', 'Al Mazrouei', 'Al Ketbi', 'Al Suwaidi', 'Al Ahbabi', 'Al Mansoori',
            // International surnames
            'Johnson', 'Smith', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
            'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
            'Singh', 'Sharma', 'Kumar', 'Patel', 'Khan', 'Ali', 'Ahmed', 'Hassan'
        ];

        $businessNotes = [
            'Regular business customer, weekly rentals',
            'Preferred customer with corporate account',
            'Tourist customer, extended stays',
            'Local resident, occasional weekend rentals',
            'Expat family, monthly long-term rentals',
            'VIP customer, premium service required',
            'Corporate account, multiple vehicle requests',
            'Short-term resident, seasonal rentals',
            'Frequent traveler, airport pickups preferred',
            'Wedding/event customer, luxury vehicle preference',
            'Construction worker, utility vehicle needs',
            'Real estate professional, client transportation',
            null, null, null // Some customers have no notes
        ];

        $this->command->info("Creating {$count} customers...");
        $progressBar = $this->command->getOutput()->createProgressBar($count);
        $progressBar->start();

        for ($i = 0; $i < $count; $i++) {
            $city = $faker->randomElement($uaeCities);
            
            // Generate area based on city
            $area = match($city) {
                'Dubai' => $faker->randomElement($dubaiAreas),
                'Abu Dhabi' => $faker->randomElement($abuDhabiAreas),
                default => $faker->streetName
            };

            // 70% chance of having email, 30% null
            $email = $faker->boolean(70) ? $faker->unique()->safeEmail : null;
            
            // 80% chance of having date of birth, 20% null
            $dateOfBirth = $faker->boolean(80) ? $faker->date('Y-m-d', '-18 years') : null;
            
            // 60% chance of having emergency contact
            $hasEmergencyContact = $faker->boolean(60);
            
            // Generate UAE phone number format
            $phoneNumber = '+971' . $faker->randomElement(['50', '52', '54', '55', '56', '58']) . $faker->numerify('#######');
            $emergencyPhone = $hasEmergencyContact ? '+971' . $faker->randomElement(['50', '52', '54', '55', '56', '58']) . $faker->numerify('#######') : null;
            
            // Generate driver's license with realistic expiry (1-3 years from now)
            $licenseExpiry = $faker->dateTimeBetween('+1 year', '+3 years')->format('Y-m-d');
            
            Customer::create([
                'team_id' => $team->id,
                'first_name' => $faker->firstName,
                'last_name' => $faker->randomElement($commonLastNames),
                'email' => $email,
                'phone' => $phoneNumber,
                'date_of_birth' => $dateOfBirth,
                'drivers_license_number' => 'DL' . $faker->numerify('########'),
                'drivers_license_expiry' => $licenseExpiry,
                'address' => $faker->buildingNumber . ', ' . $area,
                'city' => $city,
                'country' => 'United Arab Emirates',
                'emergency_contact_name' => $hasEmergencyContact ? $faker->name : null,
                'emergency_contact_phone' => $emergencyPhone,
                'status' => $faker->randomElement(['active', 'active', 'active', 'active', 'inactive']), // 80% active
                'notes' => $faker->randomElement($businessNotes),
            ]);
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info("Successfully seeded {$count} customers for team: {$team->name}");
    }
} 