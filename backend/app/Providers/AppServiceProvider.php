<?php

namespace App\Providers;

use App\Models\BookingRequest;
use App\Models\Application;
use App\Models\Listing;
use App\Policies\BookingRequestPolicy;
use App\Policies\ApplicationPolicy;
use App\Policies\ListingPolicy;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Listing::class, ListingPolicy::class);
        Gate::policy(BookingRequest::class, BookingRequestPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);

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

        RateLimiter::for('landlord_write', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(30)->by($key);
        });
    }
}
