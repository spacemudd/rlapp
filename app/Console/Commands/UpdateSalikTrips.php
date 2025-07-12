<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateSalikTrips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-salik-trips';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $output = [];
        $returnVar = 0;
        exec('node ' . base_path('scripts/salik.cjs'), $output, $returnVar);
        $this->info('Salik script executed. Output: ' . implode("\n", $output));
    }
}
