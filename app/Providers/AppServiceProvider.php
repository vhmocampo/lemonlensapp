<?php

namespace App\Providers;

use App\Services\OpenAIService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(OpenAIService::class, function ($app) {
            return new OpenAIService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        if (file_exists(base_path('routes/local.php'))) {
            Route::middleware('web')
                ->group(base_path('routes/local.php'));
        }

        // Ensure API routes are loaded
        if (file_exists(base_path('routes/api.php'))) {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        }
    }
}
