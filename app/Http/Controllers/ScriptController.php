<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ScriptController extends Controller
{
    public function run(Request $request)
    {
        $scriptPath = base_path('scripts/scrap_rta.py');
        $logPath = storage_path('logs/scrap_rta.log');

        // Remove old log
        if (file_exists($logPath)) {
            unlink($logPath);
        }

        // Run the script in the background and redirect output to log (unbuffered)
        $cmd = "python3 -u " . escapeshellarg($scriptPath) . " > " . escapeshellarg($logPath) . " 2>&1 &";
        exec($cmd);

        return response()->json(['status' => 'started']);
    }

    public function log()
    {
        $logPath = storage_path('logs/scrap_rta.log');
        $log = file_exists($logPath) ? file_get_contents($logPath) : '';
        return response()->json(['log' => $log]);
    }
}
