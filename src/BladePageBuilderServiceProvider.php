<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder;

use Illuminate\Support\ServiceProvider;

final class BladePageBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blade-page-builder.php', 'blade-page-builder');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blade-page-builder');

        $this->publishes([
            __DIR__.'/../config/blade-page-builder.php' => config_path('blade-page-builder.php'),
        ], 'blade-page-builder-config');
    }
}
