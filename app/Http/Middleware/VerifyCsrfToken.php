<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Illuminate\Session\TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        // Add some debugging for CSRF issues
        if ($this->isReading($request) || $this->runningUnitTests() || $this->inExceptArray($request)) {
            return $this->addCookieToResponse($request, $next($request));
        }

        if (!$request->hasSession() || !$request->session()->has('_token')) {
            \Log::warning('CSRF: No session or token found', [
                'url' => $request->url(),
                'method' => $request->method(),
                'has_session' => $request->hasSession(),
                'session_id' => $request->session()?->getId(),
            ]);
        }

        return parent::handle($request, $next);
    }
}
