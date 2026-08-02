<?php

declare(strict_types=1);

namespace WeArePixel\Inlay;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use WeArePixel\Inlay\Console\InstallCommand;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\View\Components\RenderPage;

final class InlayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inlay.php', 'inlay');

        $this->app->singleton(SectionRegistry::class, fn () => SectionRegistry::fromConfig());
        $this->app->singleton(InlayManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'inlay');

        $this->publishes([
            __DIR__.'/../config/inlay.php' => config_path('inlay.php'),
        ], 'inlay-config');

        Blade::component('inlay::render', RenderPage::class);

        Livewire::addNamespace('inlay', classNamespace: 'WeArePixel\\Inlay\\Livewire');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }
}
