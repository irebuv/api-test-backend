<?php

namespace App\Providers;

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
        RateLimiter::for('api', function (Request $request) {
            return [
                Limit::perMinute(120)
                    ->by($request->ip())
                    ->response(function (Request $request, array $headers) {
                        // Always return JSON for rate limit
                        return response()->json([
                            'message' => 'Too many requests. Please slow down.',
                        ], 429, $headers);
                    }),

                Limit::perMinute(240)
                    ->by(optional($request->user())->id ?? $request->ip())
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many requests (user).',
                        ], 429, $headers);
                    }),
            ];
        });

        // Limit login attempts (anti-bruteforce)
        RateLimiter::for('login', function (Request $request) {
            $key = sprintf('%s|%s', strtolower((string)$request->input('email')), $request->ip());

            // Allow 5 attempts every 15 minutes per email+IP
            return Limit::perMinutes(15, 5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in 15 minutes.',
                    ], 429, $headers);
                });
        });
    }
}
