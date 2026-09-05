<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('notifications:send-operational-alerts')
            ->hourly()
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->command('notifications:send-maintenance-reminders')
            ->hourly()
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->command('payments:expire-mercadopago-point-reservations')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\AddSecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/mercadopago/orders',
        ]);

        $middleware->alias([
            'business' => \App\Http\Middleware\EnsureBusinessContext::class,
            'business.admin' => \App\Http\Middleware\EnsureBusinessAdmin::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
