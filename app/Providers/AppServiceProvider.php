<?php

namespace App\Providers;

use App\Http\Middleware\TeamsPermission;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /** @var Kernel $kernel */
        $kernel = app()->make(Kernel::class);

        $kernel->addToMiddlewarePriorityBefore(
            SubstituteBindings::class,
            TeamsPermission::class,
        );

        // Share auth object with user and roles always present
        Inertia::share('auth', function () {
            $user = Auth::user();
            return [
                'user' => $user ? array_merge(
                    $user->toArray(),
                    [
                        'roles' => $user->getRoleNames(),
                    ]
                ) : null,
            ];
        });
    }
}
