<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WearePixel\BladePageBuilder\Support\SectionRegistry;
use WearePixel\BladePageBuilder\View\Components\RenderPage;

final class BladePageBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blade-page-builder.php', 'blade-page-builder');

        $this->app->singleton(SectionRegistry::class, fn () => SectionRegistry::fromConfig());
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blade-page-builder');

        $this->publishes([
            __DIR__.'/../config/blade-page-builder.php' => config_path('blade-page-builder.php'),
        ], 'blade-page-builder-config');

        Blade::component('page-builder::render', RenderPage::class);
    }
}
