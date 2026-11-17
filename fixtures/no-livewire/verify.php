<?php

declare(strict_types=1);

/*
| Proves Inlay installs, boots, and renders published pages in a host
| application with no Livewire at all - which the Pest suite structurally
| cannot, since Livewire is a require-dev dependency there.
|
| Run from the package root: composer test:no-livewire
*/

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Orchestra\Testbench\Foundation\Application;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\InlayServiceProvider;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\View\Components\RenderPage;

if (! is_file(__DIR__.'/vendor/autoload.php')) {
    fwrite(STDERR, "The no-Livewire fixture is not installed. Run:\n\n    composer install --working-dir=fixtures/no-livewire\n\n");
    exit(1);
}

require __DIR__.'/vendor/autoload.php';

$passed = 0;
$failures = [];
$completed = false;

// HandleExceptions renders an uncaught throwable and lets the process exit 0,
// which would report a package that fatals on boot as a pass.
register_shutdown_function(function (): void {
    global $completed;

    if ($completed !== true) {
        fwrite(STDOUT, "\nFAILED: the check did not run to completion - see the error above.\n\n");
        exit(1);
    }
});

/**
 * @param  callable(): mixed  $assertion
 */
function check(string $description, callable $assertion): void
{
    global $passed, $failures;

    try {
        $result = $assertion();
    } catch (Throwable $e) {
        $failures[] = [$description, $e::class.': '.$e->getMessage()];
        fwrite(STDOUT, "  FAIL  {$description}\n");

        return;
    }

    if ($result !== true) {
        $failures[] = [$description, 'assertion returned '.var_export($result, true).', expected true'];
        fwrite(STDOUT, "  FAIL  {$description}\n");

        return;
    }

    $passed++;
    fwrite(STDOUT, "  PASS  {$description}\n");
}

fwrite(STDOUT, "\nInlay without Livewire\n\n");

check('livewire/livewire is not installed in the fixture host', function (): bool {
    $installed = json_decode((string) file_get_contents(__DIR__.'/vendor/composer/installed.json'), true);

    $names = array_column($installed['packages'] ?? [], 'name');

    return ! in_array('livewire/livewire', $names, true) && ! is_dir(__DIR__.'/vendor/livewire');
});

check('Livewire\\Livewire is not autoloadable', fn (): bool => class_exists(Livewire::class) === false);

check('wearepixel/inlay is installed in the fixture host', function (): bool {
    $installed = json_decode((string) file_get_contents(__DIR__.'/vendor/composer/installed.json'), true);

    return in_array('wearepixel/inlay', array_column($installed['packages'] ?? [], 'name'), true);
});

// Testbench's skeleton has no vendor directory, so package discovery finds
// nothing until it's pointed at this fixture's.
Application::createVendorSymlink(null, __DIR__.'/vendor');

$app = null;

// Discovered from installed.json, as a real host would, rather than
// registered by hand.
check('a host application boots with Inlay installed', function () use (&$app): bool {
    $app = Application::create(
        basePath: null,
        options: ['enables_package_discoveries' => true],
    );

    return $app->isBooted();
});

if ($app === null) {
    fwrite(STDOUT, "\nFAILED: the application could not boot, so nothing else can be checked.\n\n");

    $completed = true;

    exit(1);
}

// After creation, not via Application::create()'s resolving callback - that
// runs before the config files load and would be overwritten.
config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
config()->set('database.default', 'testing');
config()->set('database.connections.testing', [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);

View::addLocation(__DIR__.'/views');

check('InlayServiceProvider is discovered and booted', function () use ($app): bool {
    return array_key_exists(InlayServiceProvider::class, $app->getLoadedProviders());
});

check("the package's config is merged", fn (): bool => config('inlay.admin.prefix') === 'inlay');

check('the <x-inlay::render> Blade component is registered', function (): bool {
    return Blade::getClassComponentAliases()['inlay::render']
        === RenderPage::class;
});

check("the package's migrations run", function (): bool {
    Artisan::call('migrate', ['--database' => 'testing', '--force' => true]);

    return Schema::hasTable('pages')
        && Schema::hasTable('page_sections')
        && Schema::hasTable('page_revisions');
});

check('a single-file section registers', function (): bool {
    Inlay::singleFileSection(__DIR__.'/sections/hero.blade.php');

    return app(SectionRegistry::class)->hasHandle('no-livewire-hero');
});

check('a page publishes and renders through <x-inlay::render>', function (): bool {
    $page = Page::create(['title' => 'About', 'path' => 'about']);
    $page->sections()->create([
        'handle' => 'no-livewire-hero',
        'order' => 0,
        'settings' => ['heading' => 'Rendered with no Livewire'],
    ]);

    app(PublishPage::class)->execute($page);

    $html = Blade::render('<x-inlay::render :page="$page" />', ['page' => $page]);

    return str_contains($html, 'Rendered with no Livewire')
        && str_contains($html, 'data-testid="no-livewire-hero"');
});

check('Inlay::pageRoutes() serves a published page over a real request', function (): bool {
    Inlay::pageRoutes(view: 'inlay.pages.show');

    $response = app(Kernel::class)->handle(Request::create('/about', 'GET'));

    return $response->getStatusCode() === 200
        && str_contains($response->getContent(), 'Rendered with no Livewire');
});

check('Inlay::adminRoutes() throws an actionable RuntimeException', function (): bool {
    try {
        Inlay::adminRoutes();
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), "Inlay's administration interface requires Livewire ^4.0.")
            // The wording only a genuinely Livewire-free host can see.
            && str_contains($e->getMessage(), 'Install it with:')
            && str_contains($e->getMessage(), 'composer require livewire/livewire:^4.0');
    }

    return false;
});

// A guard that throws after registering half the admin is not a guard.
check('no admin route is registered when the guard trips', function (): bool {
    Route::getRoutes()->refreshNameLookups();

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (str_starts_with((string) $route->getName(), 'inlay.admin.')) {
            return false;
        }
    }

    return true;
});

fwrite(STDOUT, "\n");

$completed = true;

if ($failures !== []) {
    fwrite(STDOUT, sprintf("%d passed, %d failed\n\n", $passed, count($failures)));

    foreach ($failures as [$description, $reason]) {
        fwrite(STDOUT, "FAILED: {$description}\n        {$reason}\n\n");
    }

    exit(1);
}

fwrite(STDOUT, "{$passed} passed - Inlay boots and renders with Livewire absent.\n\n");

exit(0);
