<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class SalikApiController extends Controller
{
    public function getBalance()
    {
        $process = new Process(['node', base_path('scripts/puppeteer_stealth.cjs')]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            return response()->json(['balance' => null, 'error' => 'Error fetching balance'], 500);
        }

        $output = $process->getOutput();
        if (preg_match('/Balance: (.*?) AED/', $output, $matches)) {
            $balance = $matches[1];
        } else {
            $balance = null;
        }

        return response()->json(['balance' => $balance]);
    }
}
