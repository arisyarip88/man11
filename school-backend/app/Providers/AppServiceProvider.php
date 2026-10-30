<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request) {
            $identifier = (string) ($request->input('login') ?? $request->input('email') ?? $request->input('username') ?? '');
            $throttleKey = Str::transliterate(Str::lower(trim($identifier)) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey)->response(function () {
                return response()->json([
                    'message' => 'Terlalu banyak percobaan login yang gagal. Silakan coba lagi dalam 1 menit.',
                    'error_code' => 'too_many_attempts',
                ], 429);
            });
        });
    }
}
