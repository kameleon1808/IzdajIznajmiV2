<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\BookingRequest;
use App\Models\Listing;
use App\Models\ListingRating;
use App\Models\RentalTransaction;
use App\Models\SavedSearch;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Observers\ListingObserver;
use App\Observers\ListingRatingObserver;
use App\Policies\ApplicationPolicy;
use App\Policies\BookingRequestPolicy;
use App\Policies\ListingPolicy;
use App\Policies\RentalTransactionPolicy;
use App\Policies\SavedSearchPolicy;
use App\Policies\ViewingRequestPolicy;
use App\Policies\ViewingSlotPolicy;
use App\Services\Geocoding\CachedGeocoder;
use App\Services\Geocoding\CachedSuggestGeocoder;
use App\Services\Geocoding\FakeGeocoder;
use App\Services\Geocoding\FakeSuggestGeocoder;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Geocoding\NominatimSuggestGeocoder;
use App\Services\Geocoding\SuggestGeocoder;
use App\Services\Search\MeiliSearchDriver;
use App\Services\Search\SearchDriver;
use App\Services\Search\SqlSearchDriver;
use App\Services\SentryReporter;
use App\Services\StructuredLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use MeiliSearch\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            $config = $app['config']->get('search.meili', []);

            return new Client($config['host'] ?? 'http://localhost:7700', $config['key'] ?? null);
        });

        $this->app->bind(SearchDriver::class, function ($app) {
            $driver = $app['config']->get('search.driver', 'sql');

            return $driver === 'meili'
                ? $app->make(MeiliSearchDriver::class)
                : $app->make(SqlSearchDriver::class);
        });

        $this->app->singleton(Geocoder::class, function ($app) {
            $config = $app['config']->get('geocoding', []);
            $driver = $config['driver'] ?? 'fake';
            $ttl = (int) ($config['cache_ttl_minutes'] ?? 1440);
            $cache = $app['cache']->store(config('cache.default'));

            $baseGeocoder = match ($driver) {
                'nominatim' => new NominatimGeocoder(
                    $config['nominatim']['endpoint'] ?? 'https://nominatim.openstreetmap.org/search',
                    $config['nominatim']['email'] ?? null,
                    $config['nominatim']['countrycodes'] ?? null,
                    (int) ($config['nominatim']['rate_limit_ms'] ?? 1200),
                    (int) ($config['nominatim']['timeout'] ?? 8),
                ),
                default => new FakeGeocoder(
                    (float) data_get($config, 'fake.base_lat', 45.0),
                    (float) data_get($config, 'fake.base_lng', 15.0),
                    (float) data_get($config, 'fake.spread_km', 120.0),
                ),
            };

            return new CachedGeocoder($baseGeocoder, $cache, $ttl);
        });

        $this->app->bind(SuggestGeocoder::class, function ($app) {
            $config = $app['config']->get('geocoding', []);
            $driver = $config['suggest_driver'] ?? 'fake';
            $ttl = (int) ($config['suggest_cache_ttl_minutes'] ?? 15);
            $cache = $app['cache']->store(config('cache.default'));

            $baseGeocoder = match ($driver) {
                'nominatim' => new NominatimSuggestGeocoder(
                    $config['nominatim']['endpoint'] ?? 'https://nominatim.openstreetmap.org/search',
                    $config['nominatim']['email'] ?? null,
                    $config['nominatim']['countrycodes'] ?? null,
                    (int) ($config['nominatim']['rate_limit_ms'] ?? 1200),
                    (int) ($config['nominatim']['timeout'] ?? 8),
                ),
                default => new FakeSuggestGeocoder,
            };

            return new CachedSuggestGeocoder($baseGeocoder, $cache, $ttl);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::failing(function (JobFailed $event): void {
            app(StructuredLogger::class)->error('queue_job_failed', [
                'connection' => $event->connectionName,
                'queue' => $event->job?->getQueue(),
                'job_name' => $event->job?->resolveName(),
                'job_uuid' => method_exists($event->job, 'uuid') ? $event->job->uuid() : null,
                'exception' => get_class($event->exception),
                'message' => $event->exception->getMessage(),
            ]);

            app(SentryReporter::class)->captureException($event->exception, [
                'connection' => $event->connectionName,
                'queue' => $event->job?->getQueue(),
                'flow' => 'queue_job',
            ]);
        });

        Listing::observe(ListingObserver::class);
        ListingRating::observe(ListingRatingObserver::class);
        Gate::policy(Listing::class, ListingPolicy::class);
        Gate::policy(SavedSearch::class, SavedSearchPolicy::class);
        Gate::policy(BookingRequest::class, BookingRequestPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(ViewingSlot::class, ViewingSlotPolicy::class);
        Gate::policy(ViewingRequest::class, ViewingRequestPolicy::class);
        Gate::policy(RentalTransaction::class, RentalTransactionPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?? $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('listings_search', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('geocode_suggest', function (Request $request) {
            return Limit::perMinute(80)->by($request->ip());
        });

        RateLimiter::for('booking_requests', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(60)->by($key);
        });

        RateLimiter::for('applications', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perHour(10)
                ->by($key)
                ->response(function () {
                    return response()->json(['message' => 'Too many applications. Please try again later.'], 429);
                });
        });

        RateLimiter::for('viewing_requests', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('rating_reports', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perDay(10)->by($key);
        });

        RateLimiter::for('verification_request', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perHour(5)->by($key);
        });

        RateLimiter::for('verification_confirm', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('landlord_write', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('chat_messages', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();
            $threadId = $request->route('conversation')?->id
                ?? $request->route('conversation')
                ?? $request->route('listing')?->id
                ?? $request->route('listing')
                ?? 'unknown';
            $key = sprintf('chat_messages:%s:%s', $userId, $threadId);
            $limit = (int) config('chat.rate_limits.messages_per_minute', 30);

            return Limit::perMinute($limit)
                ->by($key)
                ->response(function () {
                    return response()->json(['message' => 'Chat limit reached. Please slow down.'], 429);
                });
        });

        RateLimiter::for('mfa_verify', function (Request $request) {
            $key = sprintf('mfa_verify:%s:%s', $request->user()?->id ?? 'guest', $request->ip());

            return Limit::perMinute(5)
                ->by($key)
                ->response(function () {
                    $user = request()->user();
                    if ($user) {
                        app(\App\Services\FraudSignalService::class)->recordFailedMfaRateLimit($user, [
                            'ip' => request()->ip(),
                        ]);
                    }

                    return response()->json(['message' => 'Too many MFA attempts. Please wait a minute.'], 429);
                });
        });

        RateLimiter::for('mfa_sensitive', function (Request $request) {
            $key = sprintf('mfa_sensitive:%s:%s', $request->user()?->id ?? 'guest', $request->ip());

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('push_subscriptions', function (Request $request) {
            $key = sprintf('push_subscriptions:%s:%s', $request->user()?->id ?? 'guest', $request->ip());

            return Limit::perMinute(20)->by($key);
        });
    }
}
