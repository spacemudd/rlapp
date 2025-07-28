<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY');
        $validKey = '28izx09iasdasd'; // قيمة ثابتة مؤقتًا

        // Debug information
        \Log::info('API Key Debug', [
            'received_key' => $apiKey,
            'valid_key' => $validKey,
            'match' => $apiKey === $validKey
        ]);

        if (!$apiKey || $apiKey !== $validKey) {
            return response()->json([
                'message' => 'Unauthorized: Invalid API Key.',
                'debug' => [
                    'received' => $apiKey,
                    'expected' => $validKey
                ]
            ], 401);
        }

        return $next($request);
    }
}
