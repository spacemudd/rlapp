<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinesController extends Controller
{
    public function index()
    {
        $fines = Fine::orderBy('dateandtime', 'desc')->get();
        return Inertia::render('Fines', [
            'fines' => $fines,
        ]);
    }

    public function sync()
    {
        // TODO: Add actual sync logic here
        return redirect()->route('fines')->with('success', 'Fines synced successfully!');
    }
}
