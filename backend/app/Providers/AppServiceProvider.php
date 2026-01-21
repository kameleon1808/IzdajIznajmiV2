<?php

namespace App\Providers;

use App\Models\BookingRequest;
use App\Models\Application;
use App\Models\Listing;
use App\Observers\ListingObserver;
use App\Models\Rating;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Policies\BookingRequestPolicy;
use App\Policies\ApplicationPolicy;
use App\Policies\ListingPolicy;
use App\Policies\ViewingRequestPolicy;
use App\Policies\ViewingSlotPolicy;
use App\Services\Geocoding\CachedGeocoder;
use App\Services\Geocoding\FakeGeocoder;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Listing::observe(ListingObserver::class);
        Gate::policy(Listing::class, ListingPolicy::class);
        Gate::policy(BookingRequest::class, BookingRequestPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(ViewingSlot::class, ViewingSlotPolicy::class);
        Gate::policy(ViewingRequest::class, ViewingRequestPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?? $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('listings_search', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('booking_requests', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(20)->by($key);
        });

        RateLimiter::for('applications', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(20)->by($key);
        });

        RateLimiter::for('viewing_requests', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('landlord_write', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(30)->by($key);
        });
    }
}
