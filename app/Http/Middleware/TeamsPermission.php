<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamsPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!empty(auth()->user())) {
            // Get team_id from authenticated user
            $teamId = auth()->user()->team_id;
            
            // Set global team_id for permissions
            if ($teamId) {
                setPermissionsTeamId($teamId);
            }
        }
        
        return $next($request);
    }
}
