<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console\Install;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

/**
 * Writes the few files that connect Inlay to a host application. Every
 * method checks what is already there first, so running the installer a
 * second time changes nothing that was set up the first time.
 */
final class HostWiring
{
    public const ABILITY = 'inlay.manage-pages';

    public const PROVIDER_CLASS = 'App\\Providers\\InlayServiceProvider';

    public const PAGE_VIEW = 'inlay.pages.show';

    public function __construct(private readonly Filesystem $files) {}

    /**
     * Points the published config's admin guard at the ability the
     * generated service provider defines, and requires a login when the
     * application has one.
     */
    public function guardAdmin(bool $requireLogin): bool
    {
        $path = config_path('inlay.php');

        if (! $this->files->exists($path)) {
            return false;
        }

        $config = $this->files->get($path);
        $updated = str_replace("'ability' => null,", "'ability' => '".self::ABILITY."',", $config);

        if ($requireLogin) {
            $updated = preg_replace("/('admin' => \\[\\s*'prefix' => [^\\n]+\\n\\s*'middleware' => )\\['web'\\],/", "\$1['web', 'auth'],", $updated, 1) ?? $updated;
        }

        if ($updated === $config) {
            return false;
        }

        $this->files->put($path, $updated);

        return true;
    }

    /**
     * @param  array<int, string>  $pageDefinitions  class names to register
     */
    public function writeServiceProvider(array $pageDefinitions): bool
    {
        $path = app_path('Providers/InlayServiceProvider.php');

        if ($this->files->exists($path)) {
            return false;
        }

        $registrations = '';

        foreach ($pageDefinitions as $class) {
            $registrations .= "\n        Inlay::pageDefinition(\\{$class}::class);";
        }

        $source = str_replace(
            ['{{ ability }}', '{{ pageDefinitions }}', '{{ pageView }}'],
            [self::ABILITY, $registrations, self::PAGE_VIEW],
            $this->files->get(self::stub('InlayServiceProvider.php.stub')),
        );

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $source);

        if ($this->files->exists(base_path('bootstrap/providers.php'))) {
            ServiceProvider::addProviderToBootstrapFile(self::PROVIDER_CLASS, base_path('bootstrap/providers.php'));
        }

        return true;
    }

    /**
     * The view published pages and the editor preview render through.
     *
     * @param  string|null  $layout  "component:layouts.app", "extends:layouts.app", or null for a standalone page
     * @param  array<int, string>  $viteEntries
     */
    public function writePageView(?string $layout, array $viteEntries): bool
    {
        $path = resource_path('views/inlay/pages/show.blade.php');

        if ($this->files->exists($path)) {
            return false;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->pageView($layout, $viteEntries));

        return true;
    }

    /**
     * Adds Inlay's routes to the end of routes/web.php. In a fresh
     * application the stock welcome route is removed so the homepage is
     * the one editors build.
     */
    public function writeRoutes(bool $replaceWelcomeRoute): bool
    {
        $path = base_path('routes/web.php');

        if (! $this->files->exists($path)) {
            return false;
        }

        $routes = $this->files->get($path);

        if (str_contains($routes, 'Inlay::adminRoutes()')) {
            return false;
        }

        if ($replaceWelcomeRoute) {
            $routes = preg_replace("/Route::get\\('\\/',\\s*function\\s*\\(\\)\\s*\\{\\s*return view\\('welcome'\\);\\s*\\}\\);\\s*/", '', $routes) ?? $routes;
        }

        $routes = str_replace(
            "use Illuminate\\Support\\Facades\\Route;\n",
            "use Illuminate\\Support\\Facades\\Route;\nuse WeArePixel\\Inlay\\Facades\\Inlay;\n",
            $routes,
        );

        $routes = rtrim($routes)."\n\nInlay::adminRoutes();\nInlay::previewLinkRoutes();\n\n// Last: it answers every URL no other route has claimed.\nInlay::pageRoutes();\n";

        $this->files->put($path, $routes);

        return true;
    }

    public function setCssFramework(string $css): bool
    {
        $path = base_path('.env');

        if (! $this->files->exists($path) || str_contains($this->files->get($path), 'INLAY_CSS_FRAMEWORK=')) {
            return false;
        }

        $this->files->append($path, "\nINLAY_CSS_FRAMEWORK={$css}\n");

        return true;
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
            $section = $this->yieldedSection($view);

            return "@extends('{$view}')\n\n@section('{$section}')\n    {!! \$composition !!}\n@endsection\n";
        }

        $assets = $viteEntries === []
            ? ''
            : "\n    @vite(['".implode("', '", $viteEntries)."'])";

        return str_replace('{{ assets }}', $assets, $this->files->get(self::stub('page.blade.php.stub')));
    }

    /**
     * The section a layout yields its main content into - the first
     *
     * @yield in the file, "content" by convention.
     */
    private function yieldedSection(string $view): string
    {
        $path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');

        if ($this->files->exists($path) && preg_match("/@yield\\(\\s*'([^']+)'/", $this->files->get($path), $match) === 1) {
            return $match[1];
        }

        return 'content';
    }

    private static function stub(string $name): string
    {
        return dirname(__DIR__, 3).'/stubs/install/'.$name;
    }
}
