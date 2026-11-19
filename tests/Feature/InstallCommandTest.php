<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use WeArePixel\Inlay\Models\Page;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/inlay-install-'.bin2hex(random_bytes(4));
    $this->files = new Filesystem;

    foreach (['config', 'routes', 'bootstrap', 'resources/css', 'resources/js', 'app/Providers'] as $directory) {
        $this->files->ensureDirectoryExists("{$this->base}/{$directory}");
    }

    $this->files->put("{$this->base}/routes/web.php", "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', function () {\n    return view('welcome');\n});\n");
    $this->files->put("{$this->base}/bootstrap/providers.php", "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");
    $this->files->put("{$this->base}/.env", "APP_NAME=Test\n");
    $this->files->put("{$this->base}/vite.config.js", 'export default {};');
    $this->files->put("{$this->base}/resources/css/app.css", '@import "tailwindcss";');
    $this->files->put("{$this->base}/resources/js/app.js", '');
    $this->files->put("{$this->base}/package.json", json_encode(['devDependencies' => ['tailwindcss' => '^4.0.0']]));
    $this->files->put("{$this->base}/composer.json", json_encode(['require' => ['livewire/livewire' => '^4.0']]));

    // vendor:publish targets the path computed when the app booted, before
    // this test moved the base path, so the published copy is placed here.
    $this->files->copy(dirname(__DIR__, 2).'/config/inlay.php', "{$this->base}/config/inlay.php");

    $this->app->setBasePath($this->base);
    $this->app->useAppPath("{$this->base}/app");
});

afterEach(fn () => $this->files->deleteDirectory($this->base));

it('wires a fresh application up in one command', function () {
    $this->artisan('inlay:install', ['--starter' => 'landing', '--demo' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Tailwind CSS')
        ->assertSuccessful();

    $routes = $this->files->get("{$this->base}/routes/web.php");

    expect($routes)
        ->toContain('Inlay::adminRoutes();')
        ->toContain('Inlay::pageRoutes();')
        ->not->toContain("view('welcome')")
        ->and($this->files->get("{$this->base}/bootstrap/providers.php"))->toContain('App\\Providers\\InlayServiceProvider::class')
        ->and($this->files->get("{$this->base}/app/Providers/InlayServiceProvider.php"))
        ->toContain("Gate::define('inlay.manage-pages'")
        ->toContain('Inlay::pageDefinition(\\App\\Inlay\\LandingPage::class)')
        ->and($this->files->get("{$this->base}/resources/views/inlay/pages/show.blade.php"))->toContain("@vite(['resources/css/app.css', 'resources/js/app.js'])")
        ->and($this->files->get("{$this->base}/config/inlay.php"))->toContain("'ability' => 'inlay.manage-pages',")
        ->and($this->files->get("{$this->base}/.env"))->toContain('INLAY_CSS_FRAMEWORK=tailwind')
        ->and(Page::query()->where('is_homepage', true)->whereHas('revisions')->exists())->toBeTrue();
});

it('changes nothing it already set up when run again', function () {
    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])->assertSuccessful();
    $firstRoutes = $this->files->get("{$this->base}/routes/web.php");

    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])
        ->expectsOutputToContain('already set up')
        ->assertSuccessful();

    expect($this->files->get("{$this->base}/routes/web.php"))->toBe($firstRoutes)
        ->and(substr_count($this->files->get("{$this->base}/bootstrap/providers.php"), 'InlayServiceProvider'))->toBe(1);
});

it('renders pages inside an existing layout and requires login when the app has one', function () {
    $this->files->put("{$this->base}/package.json", json_encode(['dependencies' => ['bootstrap' => '^5.3.0']]));
    $this->files->put("{$this->base}/composer.json", json_encode(['require' => ['laravel/fortify' => '^1.0']]));
    $this->files->ensureDirectoryExists("{$this->base}/resources/views/layouts");
    $this->files->put("{$this->base}/resources/views/layouts/app.blade.php", "<html><body>@yield('main')</body></html>");

    $this->artisan('inlay:install', ['--starter' => 'none', '--layout' => 'extends:layouts.app', '--no-interaction' => true])
        ->expectsOutputToContain('Bootstrap')
        ->assertSuccessful();

    expect($this->files->get("{$this->base}/resources/views/inlay/pages/show.blade.php"))
        ->toContain("@extends('layouts.app')")
        ->toContain("@section('main')")
        ->and($this->files->get("{$this->base}/config/inlay.php"))->toContain("'middleware' => ['web', 'auth'],");
});

it('leaves the welcome route alone in an application that has its own routes', function () {
    $this->files->append("{$this->base}/routes/web.php", "\nRoute::get('/dashboard', fn () => 'x');\n");

    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])->assertSuccessful();

    expect($this->files->get("{$this->base}/routes/web.php"))->toContain("view('welcome')");
});

it('rejects an unknown starter without installing anything', function () {
    $this->artisan('inlay:install', ['--starter' => 'nope', '--no-interaction' => true])->assertFailed();

    expect("{$this->base}/app/Providers/InlayServiceProvider.php")->not->toBeFile();
});

it('does not touch a routes file that already uses Inlay', function () {
    $routes = "<?php\r\n\r\nuse WeArePixel\\Inlay\\Facades\\Inlay;\r\n\r\nInlay::routes();\r\n";
    $this->files->put("{$this->base}/routes/web.php", $routes);

    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])->assertSuccessful();

    expect($this->files->get("{$this->base}/routes/web.php"))->toBe($routes);
});

it('appends fully qualified route calls that work without an import', function () {
    $this->files->put("{$this->base}/routes/web.php", "<?php\n\nRoute::get('/x', fn () => 'x');\n");

    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])->assertSuccessful();

    expect($this->files->get("{$this->base}/routes/web.php"))->toContain('\\WeArePixel\\Inlay\\Facades\\Inlay::pageRoutes();');
});

it('tells you to register the provider when the app has no bootstrap/providers.php', function () {
    $this->files->delete("{$this->base}/bootstrap/providers.php");

    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])
        ->expectsOutputToContain('NEEDS YOU')
        ->expectsOutputToContain("to the 'providers' array in config/app.php")
        ->assertSuccessful();
});

it('renders into a layout\'s content section, not its title', function () {
    $this->files->ensureDirectoryExists("{$this->base}/resources/views/layouts");
    $this->files->put("{$this->base}/resources/views/layouts/site.blade.php", "<title>@yield('title', 'Site')</title><main>@yield('main')</main>");

    $this->artisan('inlay:install', ['--starter' => 'none', '--layout' => 'extends:layouts.site', '--no-interaction' => true])->assertSuccessful();

    expect($this->files->get("{$this->base}/resources/views/inlay/pages/show.blade.php"))->toContain("@section('main')");
});

it('tells you which page type to add when the provider already exists', function () {
    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])->assertSuccessful();

    $this->artisan('inlay:install', ['--starter' => 'landing', '--no-interaction' => true])
        ->expectsOutputToContain('Add Inlay::pageDefinition(\\App\\Inlay\\LandingPage::class);')
        ->assertSuccessful();
});

it('copies the app font and brand colour into the admin theme', function () {
    $this->files->put("{$this->base}/resources/css/app.css", "@import \"tailwindcss\";\n\n@theme {\n    --font-sans: 'Almarai', sans-serif;\n    --color-brand: #334735;\n}\n");

    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])->assertSuccessful();
    $this->artisan('inlay:install', ['--starter' => 'none', '--no-interaction' => true])->assertSuccessful();

    $css = $this->files->get("{$this->base}/resources/css/app.css");

    expect($css)
        ->toContain("--inlay-font: 'Almarai', sans-serif;")
        ->toContain('--inlay-accent: #334735;')
        ->and(substr_count($css, '.inlay {'))->toBe(1);
});
