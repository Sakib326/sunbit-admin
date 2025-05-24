<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;

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

        if (!app()->runningInConsole()) {
            if (!Storage::disk('public')->exists('country-flags') && !file_exists(public_path('storage/country-flags'))) {
                Storage::disk('public')->makeDirectory('country-flags');
            }

            if (!Storage::disk('public')->exists('state-images') && !file_exists(public_path('storage/state-images'))) {
                Storage::disk('public')->makeDirectory('state-images');
            }
        }

    }
}
