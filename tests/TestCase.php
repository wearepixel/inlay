<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\McpServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase as Orchestra;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\InlayServiceProvider;
use WeArePixel\Inlay\Models\Page;

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
            McpServiceProvider::class,
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
        // browser test that configures config('inlay.admin.middleware') with 'auth'.
        Route::get('/login', fn () => 'login')->name('login');

        // A genuine, cookie-based login flow (distinct from the '/login'
        // redirect stub above) - used only by the browser test proving a
        // real session survives a Livewire action, as opposed to the
        // in-process actingAs() shortcut used elsewhere in this file. Needs
        // the real 'web' group so the session cookie set here is actually
        // encrypted the same way a real browser session would be - the bug
        // this exists to catch only reproduces against a genuinely
        // encrypted cookie, not actingAs()'s shared in-memory guard state.
        Route::middleware('web')->group(function () {
            Route::get('/test-login', fn () => '<form method="POST" action="/test-login">'
                .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                .'<input type="email" name="email" data-test="email">'
                .'<input type="password" name="password" data-test="password">'
                .'<button type="submit" data-test="login-submit">Log in</button>'
                .'</form>');

            Route::post('/test-login', function (Request $request) {
                if (! Auth::attempt($request->only('email', 'password'))) {
                    abort(401);
                }

                $request->session()->regenerate();

                return redirect('/inlay-secure/pages');
            });
        });

        // Stands in for a host application's own public page route,
        // embedding the package's render component exactly as the README
        // documents - used by the browser tests that verify draft/publish
        // divergence against real, host-owned rendering. Needs the 'web'
        // middleware group for SubstituteBindings to resolve {page}
        // implicitly - without it, the closure's Page $page is just an
        // empty, unbound model rather than a 404 or the real record.
        Route::middleware('web')->get('/site/{page}', fn (Page $page) => view('public-page', ['page' => $page]))->name('site.page');

        // Only the admin routes are registered here, deliberately not the
        // combined Inlay::routes() - a root-level Inlay::pageRoutes() is a
        // genuine catch-all, and registering it in this file's shared
        // defineRoutes() would shadow every other route below it (the
        // 'inlay-secure' group, this file's own /login and /site/{page}
        // stubs) for the entire suite. Tests that specifically exercise
        // public Page routing register Inlay::pageRoutes() themselves,
        // scoped to just that test.
        Inlay::adminRoutes();

        // A second, permanently-registered copy of the same admin routes
        // guarded by 'auth' - used only by the browser test that proves
        // config('inlay.admin.middleware') actually protects the admin. This
        // exists as a fixed route group (rather than toggling
        // config('inlay.admin.middleware') per test) because
        // Inlay::adminRoutes() bakes the configured middleware into the
        // route group at registration time, during this same
        // defineRoutes() call - Pest's beforeEach() runs after the
        // application has already booted, too late to influence it.
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
        // real user against config('inlay.admin.middleware').
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
