<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class FineController extends Controller
{
    public function index()
    {
        $fines = Fine::orderBy('created_at', 'desc')->get();
        return inertia('Fines', compact('fines'));
    }

    public function runScript()
    {
        try {
            // تشغيل السكريبت
            $output = shell_exec('cd ' . base_path() . ' && python3 scripts/scrap_rta.py 2>&1');

            // حفظ وقت آخر تحديث
            Storage::put('last_sync.txt', Carbon::now()->toISOString());

            return response()->json([
                'success' => true,
                'message' => 'Script executed successfully',
                'output' => $output,
                'last_sync' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Script execution failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLastSync()
    {
        $lastSync = Storage::exists('last_sync.txt')
            ? Storage::get('last_sync.txt')
            : null;

        return response()->json([
            'last_sync' => $lastSync
        ]);
    }
}
