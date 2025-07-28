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
        $validKey = env('MY_API_KEY');

        if (!$apiKey || $apiKey !== $validKey) {
            return response()->json([
                'message' => 'Unauthorized: Invalid API Key.'
            ], 401);
        }

        return $next($request);
    }
}
