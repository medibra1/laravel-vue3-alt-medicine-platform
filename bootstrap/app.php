<?php

use App\Domains\Auth\Http\Middleware\EnsureCenterAccess;
use App\Domains\Auth\Http\Middleware\EnsureModuleEnabled;
use App\Domains\Scheduling\Console\Commands\SendAppointmentReminders;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Domain commands live outside app/Console, so they aren't auto-discovered.
    ->withCommands([
        SendAppointmentReminders::class,
    ])
    // Needs the server cron `* * * * * php artisan schedule:run` in production.
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(SendAppointmentReminders::class)->everyFifteenMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'center.access' => EnsureCenterAccess::class,
            'module' => EnsureModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
