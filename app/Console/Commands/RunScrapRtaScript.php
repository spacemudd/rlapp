<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunScrapRtaScript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:scrap-rta-script';
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
        $scriptPath = base_path('scripts/scrap_rta.py');
        $output = [];
        $returnVar = 0;
        exec("python3 $scriptPath", $output, $returnVar);
        $this->info(implode(PHP_EOL, $output));
        return $returnVar;
    }
}
