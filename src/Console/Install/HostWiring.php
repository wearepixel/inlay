<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console\Install;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

/**
 * Writes the few files that connect Inlay to a host application. Each step
 * checks what is already there first, so running the installer again
 * changes nothing it set up before, and a step it cannot do safely is left
 * as an instruction instead of a guess.
 */
final class HostWiring
{
    public const ABILITY = 'inlay.manage-pages';

    public const PROVIDER_CLASS = 'App\\Providers\\InlayServiceProvider';

    public const PAGE_VIEW = 'inlay.pages.show';

    /** Yields that hold head content rather than the page body. */
    private const HEAD_YIELDS = ['title', 'meta', 'head', 'styles', 'scripts', 'description'];

    public function __construct(private readonly Filesystem $files) {}

    /**
     * Points the published config's admin guard at the ability the
     * generated service provider defines, and requires a login when the
     * application has one.
     */
    public function guardAdmin(bool $requireLogin): StepResult
    {
        $path = config_path('inlay.php');

        if (! $this->files->exists($path)) {
            return StepResult::needsYou("Publish the config (php artisan vendor:publish --tag=inlay-config) and set 'admin.ability' to '".self::ABILITY."'.");
        }

        $config = $this->files->get($path);

        if (str_contains($config, "'ability' => '".self::ABILITY."'")) {
            return StepResult::alreadySetUp();
        }

        if (! str_contains($config, "'ability' => null,")) {
            return StepResult::needsYou("config/inlay.php is customised - set 'admin.ability' to '".self::ABILITY."' yourself.");
        }

        $updated = str_replace("'ability' => null,", "'ability' => '".self::ABILITY."',", $config);

        if ($requireLogin) {
            $updated = str_replace("'middleware' => ['web'],\n\n        /*\n        | 'ability'", "'middleware' => ['web', 'auth'],\n\n        /*\n        | 'ability'", $updated);
        }

        $this->files->put($path, $updated);

        return StepResult::done();
    }

    /**
     * @param  array<int, string>  $pageDefinitions  class names to register
     */
    public function writeServiceProvider(array $pageDefinitions): StepResult
    {
        $path = app_path('Providers/InlayServiceProvider.php');

        if ($this->files->exists($path)) {
            return $this->checkExistingProvider($path, $pageDefinitions);
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->providerSource($pageDefinitions));

        if (! $this->files->exists(base_path('bootstrap/providers.php'))) {
            return StepResult::needsYou('Add '.self::PROVIDER_CLASS."::class to the 'providers' array in config/app.php.");
        }

        ServiceProvider::addProviderToBootstrapFile(self::PROVIDER_CLASS, base_path('bootstrap/providers.php'));

        return StepResult::done();
    }

    /**
     * The view published pages and the editor preview render through.
     *
     * @param  string|null  $layout  "component:layouts.app", "extends:layouts.app", or null for a standalone page
     * @param  array<int, string>  $viteEntries
     */
    public function writePageView(?string $layout, array $viteEntries): StepResult
    {
        $path = resource_path('views/inlay/pages/show.blade.php');

        if ($this->files->exists($path)) {
            return StepResult::alreadySetUp();
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->pageView($layout, $viteEntries));

        return StepResult::done();
    }

    /**
     * Adds Inlay's routes to the end of routes/web.php, fully qualified so
     * no import is needed. In a fresh application the stock welcome route
     * is removed so the homepage is the one editors build.
     */
    public function writeRoutes(bool $replaceWelcomeRoute): StepResult
    {
        $path = base_path('routes/web.php');

        if (! $this->files->exists($path)) {
            return StepResult::needsYou('Register Inlay::adminRoutes(), Inlay::previewLinkRoutes() and, last of all, Inlay::pageRoutes() in your routes.');
        }

        $routes = $this->files->get($path);

        if (str_contains($routes, 'Inlay::')) {
            return StepResult::alreadySetUp();
        }

        if ($replaceWelcomeRoute) {
            $routes = $this->withoutWelcomeRoute($routes);
        }

        $routes = rtrim($routes)."\n\n"
            ."\\WeArePixel\\Inlay\\Facades\\Inlay::adminRoutes();\n"
            ."\\WeArePixel\\Inlay\\Facades\\Inlay::previewLinkRoutes();\n\n"
            ."// Keep this last: it answers every URL no other route has claimed.\n"
            ."\\WeArePixel\\Inlay\\Facades\\Inlay::pageRoutes();\n";

        $this->files->put($path, $routes);

        return StepResult::done();
    }

    public function setCssFramework(string $css): StepResult
    {
        $path = base_path('.env');

        if (! $this->files->exists($path)) {
            return StepResult::needsYou("Set INLAY_CSS_FRAMEWORK={$css} in your environment.");
        }

        if (preg_match('/^INLAY_CSS_FRAMEWORK=/m', $this->files->get($path)) === 1) {
            return StepResult::alreadySetUp();
        }

        $this->files->append($path, "\nINLAY_CSS_FRAMEWORK={$css}\n");

        return StepResult::done();
    }

    /**
     * An existing provider is left alone, but a starter's page type it does
     * not register yet is reported, since the starter's pages need it.
     *
     * @param  array<int, string>  $pageDefinitions
     */
    private function checkExistingProvider(string $path, array $pageDefinitions): StepResult
    {
        $source = $this->files->get($path);

        foreach ($pageDefinitions as $class) {
            if (! str_contains($source, class_basename($class).'::class')) {
                return StepResult::needsYou("Add Inlay::pageDefinition(\\{$class}::class); to app/Providers/InlayServiceProvider.php.");
            }
        }

        return StepResult::alreadySetUp();
    }

    /**
     * @param  array<int, string>  $pageDefinitions
     */
    private function providerSource(array $pageDefinitions): string
    {
        $registrations = '';

        foreach ($pageDefinitions as $class) {
            $registrations .= "\n        Inlay::pageDefinition(\\{$class}::class);";
        }

        return str_replace(
            ['{{ ability }}', '{{ pageDefinitions }}', '{{ pageView }}'],
            [self::ABILITY, $registrations, self::PAGE_VIEW],
            $this->files->get(self::stub('InlayServiceProvider.php.stub')),
        );
    }

    private function withoutWelcomeRoute(string $routes): string
    {
        $welcomeRoute = '~Route::get\(\s*\'/\',\s*function\s*\(\)\s*\{\s*return view\(\'welcome\'\);\s*\}\);\s*~';

        return preg_replace($welcomeRoute, '', $routes) ?? $routes;
    }

    /**
     * @param  array<int, string>  $viteEntries
     */
    private function pageView(?string $layout, array $viteEntries): string
    {
        if ($layout !== null && str_starts_with($layout, 'component:')) {
            $component = substr($layout, strlen('component:'));

            return "<x-{$component}>\n    {!! \$composition !!}\n</x-{$component}>\n";
        }

        if ($layout !== null && str_starts_with($layout, 'extends:')) {
            $view = substr($layout, strlen('extends:'));
            $section = $this->contentSection($view);

            return "@extends('{$view}')\n\n@section('{$section}')\n    {!! \$composition !!}\n@endsection\n";
        }

        $assets = $viteEntries === []
            ? ''
            : "\n    @vite(['".implode("', '", $viteEntries)."'])";

        return str_replace('{{ assets }}', $assets, $this->files->get(self::stub('page.blade.php.stub')));
    }

    /**
     * The section a layout yields its page body into: "content" when it has
     * one, otherwise the first yield that is not head content like the title.
     */
    private function contentSection(string $view): string
    {
        $path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');

        if (! $this->files->exists($path)) {
            return 'content';
        }

        preg_match_all('~@yield\(\s*[\'"]([^\'"]+)[\'"]~', $this->files->get($path), $matches);
        $yields = $matches[1];

        if (in_array('content', $yields, true)) {
            return 'content';
        }

        foreach ($yields as $yield) {
            if (! in_array($yield, self::HEAD_YIELDS, true)) {
                return $yield;
            }
        }

        return 'content';
    }

    private static function stub(string $name): string
    {
        return dirname(__DIR__, 3).'/stubs/install/'.$name;
    }
}
