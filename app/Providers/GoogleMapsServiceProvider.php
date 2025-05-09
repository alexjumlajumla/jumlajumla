<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class GoogleMapsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/google-maps.php', 'google-maps'
        );
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/google-maps.php' => config_path('google-maps.php'),
        ], 'config');
    }
} 