<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Clients\LemonbaseClient;

class LemonbaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(LemonbaseClient::class, function ($app) {
            return new LemonbaseClient();
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/lemonbase.php',
            'lemonbase'
        );
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/lemonbase.php' => config_path('lemonbase.php'),
        ], 'lemonbase-config');
    }
}
