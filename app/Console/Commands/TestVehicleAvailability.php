<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TestVehicleAvailability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:vehicle-availability {--seed : Seed test data first} {--coverage : Run with coverage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run vehicle availability module tests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚗 Starting Vehicle Availability Module Tests...');
        $this->newLine();

        // Seed test data if requested
        if ($this->option('seed')) {
            $this->info('📊 Seeding test data...');
            Artisan::call('db:seed', ['--class' => 'VehicleAvailabilityTestSeeder']);
            $this->info('✅ Test data seeded successfully!');
            $this->newLine();
        }

        // Run the tests
        $this->info('🧪 Running vehicle availability tests...');
        
        $testOptions = [
            'path' => 'tests/Feature/VehicleAvailabilityTest.php,tests/Feature/VehicleSelectionComponentTest.php'
        ];
        
        if ($this->option('coverage')) {
            $testOptions['--coverage'] = true;
        }
        
        $exitCode = Artisan::call('test', $testOptions);

        if ($exitCode === 0) {
            $this->newLine();
            $this->info('✅ All vehicle availability tests passed!');
            $this->newLine();
            $this->displayTestSummary();
        } else {
            $this->newLine();
            $this->error('❌ Some tests failed. Check the output above for details.');
        }

        return $exitCode;
    }

    /**
     * Display a summary of what was tested
     */
    private function displayTestSummary()
    {
        $this->info('📋 Test Summary:');
        $this->table(
            ['Feature', 'Description'],
            [
                ['Vehicle Search API', 'Search vehicles with availability status'],
                ['Conflict Detection', 'Detect active contracts and reservations'],
                ['Availability Checking', 'Check specific vehicle availability'],
                ['Similar Vehicles', 'Find alternative vehicles when unavailable'],
                ['Date Validation', 'Validate date parameters and ranges'],
                ['Authentication', 'Require proper authentication'],
                ['Multiple Conflicts', 'Handle multiple overlapping conflicts'],
                ['Inactive Records', 'Ignore completed/canceled records'],
                ['Component Integration', 'Test frontend component integration']
            ]
        );

        $this->newLine();
        $this->info('🎯 Key Test Scenarios:');
        $this->line('• Available vehicles show correct status');
        $this->line('• Unavailable vehicles show conflict details');
        $this->line('• Date range overlaps are detected correctly');
        $this->line('• Similar vehicles are suggested when needed');
        $this->line('• Authentication is required for all endpoints');
        $this->line('• Inactive contracts/reservations are ignored');
        $this->line('• Multiple conflicts are handled properly');
    }
}
