<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {
        // Nightly pull, as the spec requires. On shared cPanel this needs a
        // real cron entry calling `artisan schedule:run` every minute; the
        // WordPress pseudo-cron on the shop is not involved and must not be,
        // because a low traffic site fires it late or not at all.
        $schedule->command('wgh:sync')
            ->dailyAt('02:15')
            ->timezone('Africa/Accra')
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->create();
