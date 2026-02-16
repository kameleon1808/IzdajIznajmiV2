<?php

use App\Console\Commands\DbReportIndexesCommand;
use App\Console\Commands\ExpireListingsCommand;
use App\Console\Commands\GeocodeListingsCommand;
use App\Console\Commands\RecomputeBadgesCommand;
use App\Console\Commands\SavedSearchMatchCommand;
use App\Console\Commands\SearchListingsReindexCommand;
use App\Console\Commands\SearchListingsSyncMissingCommand;
use App\Http\Middleware\ChatAttachmentRateLimit;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Services\SentryReporter;
use App\Services\StructuredLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'middleware' => ['web', 'auth:sanctum'],
    ])
    ->withCommands([
        DbReportIndexesCommand::class,
        ExpireListingsCommand::class,
        \App\Console\Commands\SendNotificationDigestCommand::class,
        GeocodeListingsCommand::class,
        RecomputeBadgesCommand::class,
        SavedSearchMatchCommand::class,
        SearchListingsReindexCommand::class,
        SearchListingsSyncMissingCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('listings:expire')->dailyAt('02:00');
        $schedule->command('badges:recompute')->dailyAt('03:00');
        $schedule->command('notifications:digest --frequency=daily')->dailyAt('09:00');
        $schedule->command('notifications:digest --frequency=weekly')->weeklyOn(1, '09:00'); // Monday
        $schedule->command('saved-searches:match')->everyFifteenMinutes(); //
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            ValidatePathEncoding::class,
            TrustProxies::class,
            RequestIdMiddleware::class,
            SecurityHeadersMiddleware::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);

        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'chat_attachments' => ChatAttachmentRateLimit::class,
            'mfa' => \App\Http\Middleware\EnsureMfaVerified::class,
            'admin_mfa' => \App\Http\Middleware\RequireMfaForAdmin::class,
            'session_activity' => \App\Http\Middleware\SessionActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

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
                'request_id' => request()?->attributes->get('request_id') ?? request()?->header('X-Request-Id'),
            ]);

            app(SentryReporter::class)->captureException($e, [
                'status' => $status,
                'flow' => 'http_exception',
            ]);
        });
    })->create();
