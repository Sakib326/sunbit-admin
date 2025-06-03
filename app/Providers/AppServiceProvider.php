<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Blade;

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

        Blade::directive('clean', function ($expression) {
            return "<?php echo preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/', '', mb_convert_encoding($expression, 'UTF-8', 'auto')); ?>";
        });

    }
}
