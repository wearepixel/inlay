<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Tests;

use Illuminate\Support\Facades\Route;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase as Orchestra;
use WearePixel\Inlay\Facades\Inlay;
use WearePixel\Inlay\InlayServiceProvider;
use WearePixel\Inlay\Models\Page;

abstract class TestCase extends Orchestra
{
    use WithLaravelMigrations;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            InlayServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['view']->addLocation(__DIR__.'/Fixtures/views');
    }

    protected function defineRoutes($router): void
    {
        // A named 'login' route is all Illuminate\Auth\Middleware\Authenticate
        // needs to redirect an unauthenticated request to - used only by the
        // browser test that configures config('inlay.middleware') with 'auth'.
        Route::get('/login', fn () => 'login')->name('login');

        // Stands in for a host application's own public page route,
        // embedding the package's render component exactly as the README
        // documents - used by the browser tests that verify draft/publish
        // divergence against real, host-owned rendering. Needs the 'web'
        // middleware group for SubstituteBindings to resolve {page}
        // implicitly - without it, the closure's Page $page is just an
        // empty, unbound model rather than a 404 or the real record.
        Route::middleware('web')->get('/site/{page}', fn (Page $page) => view('public-page', ['page' => $page]))->name('site.page');

        Inlay::routes();

        // A second, permanently-registered copy of the same admin routes
        // guarded by 'auth' - used only by the browser test that proves
        // config('inlay.middleware') actually protects the admin. This
        // exists as a fixed route group (rather than toggling
        // config('inlay.middleware') per test) because Inlay::routes()
        // bakes the configured middleware into the route group at
        // registration time, during this same defineRoutes() call -
        // Pest's beforeEach() runs after the application has already
        // booted, too late to influence it.
        Route::group([
            'prefix' => 'inlay-secure',
            'as' => 'inlay-secure.',
            'middleware' => ['web', 'auth'],
        ], __DIR__.'/../routes/admin.php');
    }

    protected function defineDatabaseMigrations(): void
    {
        // The framework's own users/cache/jobs tables - not used by most of
        // this suite, but needed by the browser tests that authenticate a
        // real user against config('inlay.middleware').
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
