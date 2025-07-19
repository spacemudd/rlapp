<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\IFRSSeeder;

class InitializeIFRS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ifrs:init {--force : Force initialization even if already initialized}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize the IFRS accounting system with required entities and accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏗️  Initializing IFRS Accounting System...');
        $this->newLine();
        
        try {
            // Check if already initialized (unless forced)
            if (!$this->option('force')) {
                $entity = \IFRS\Models\Entity::first();
                if ($entity) {
                    $this->info('✅ IFRS system appears to be already initialized.');
                    
                    if (!$this->confirm('Do you want to continue initialization anyway?', false)) {
                        $this->info('Initialization cancelled.');
                        return 0;
                    }
                }
            }
            
            // Initialize the system using the seeder
            $this->info('Running IFRS seeder...');
            $seeder = new IFRSSeeder();
            $seeder->setCommand($this);
            $seeder->run();
            
            $this->newLine();
            $this->info('💡 Next steps:');
            $this->line('   - Create customer invoices to test the system');
            $this->line('   - Set up bank and cash accounts via /accounting dashboard');
            $this->line('   - Configure chart of accounts as needed');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to initialize IFRS system:');
            $this->error($e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error('Stack trace:');
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
        
        return 0;
    }
}
