<?php

declare(strict_types=1);

namespace WeArePixel\Inlay;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use WeArePixel\Inlay\Console\InstallCommand;
use WeArePixel\Inlay\Contracts\Screenshotter;
use WeArePixel\Inlay\Declarative\DeclarativeBlocks;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Screenshots\BrowsershotScreenshotter;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\LivewireRequirement;
use WeArePixel\Inlay\View\Components\RenderPage;

final class InlayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inlay.php', 'inlay');

        $this->app->singleton(BlockRegistry::class, function () {
            $registry = BlockRegistry::fromConfig();
            $registry->useDeclarativeLoader(fn () => app(DeclarativeBlocks::class)->definitions());

            return $registry;
        });
        $this->app->singleton(PageDefinitionRegistry::class);
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

        // Livewire is optional - only the admin needs it, and adminRoutes()
        // fails loudly through this same check when it's missing.
        if (LivewireRequirement::isSatisfied()) {
            Livewire::addNamespace('inlay', classNamespace: 'WeArePixel\\Inlay\\Livewire');
        }

        if (config('inlay.mcp.screenshots.driver') === 'browsershot' && ! $this->app->bound(Screenshotter::class)) {
            $this->app->singleton(Screenshotter::class, fn () => new BrowsershotScreenshotter(
                (array) config('inlay.mcp.screenshots.browsershot', []),
            ));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }
}
