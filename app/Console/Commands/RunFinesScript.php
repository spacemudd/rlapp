<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class RunFinesScript extends Command
{
    protected $signature = 'fines:run-script';
    protected $description = 'Run the fines scraping script automatically';

    public function handle()
    {
        $this->info('Starting fines script execution...');

        try {
            // تشغيل السكريبت
            $output = shell_exec('cd ' . base_path() . ' && python3 scripts/scrap_rta.py 2>&1');

            // حفظ وقت آخر تحديث
            Storage::put('last_sync.txt', Carbon::now()->toISOString());

            $this->info('Script executed successfully at: ' . Carbon::now()->toDateTimeString());
            $this->info('Output: ' . $output);

            return 0;
        } catch (\Exception $e) {
            $this->error('Script execution failed: ' . $e->getMessage());
            return 1;
        }
    }
}
