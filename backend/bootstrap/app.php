<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Console\Scheduling\Schedule;
use App\Console\Commands\ExpireListingsCommand;
use App\Console\Commands\GeocodeListingsCommand;
use App\Console\Commands\SavedSearchMatchCommand;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use App\Services\StructuredLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ExpireListingsCommand::class,
        \App\Console\Commands\SendNotificationDigestCommand::class,
        GeocodeListingsCommand::class,
        SavedSearchMatchCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('listings:expire')->dailyAt('02:00');
        $schedule->command('notifications:digest --frequency=daily')->dailyAt('09:00');
        $schedule->command('notifications:digest --frequency=weekly')->weeklyOn(1, '09:00'); // Monday
        $schedule->command('saved-searches:match')->everyFifteenMinutes(); //
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            ValidatePathEncoding::class,
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);

        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $e) {
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof HttpResponseException) {
                return;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($status < 500) {
                return;
            }

            app(StructuredLogger::class)->error('unhandled_exception', [
                'status' => $status,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'url' => request()?->fullUrl(),
            ]);

            if (config('services.sentry.enabled', false) && app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    })->create();
