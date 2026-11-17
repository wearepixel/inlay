<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console\Install;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;

/**
 * Reads the host application's package.json, composer.json, routes and views
 * to work out how Inlay should be wired in. It only reads files; nothing is
 * written here.
 */
final class StackDetector
{
    public function __construct(private readonly Filesystem $files) {}

    public function detect(): Stack
    {
        $npm = $this->npmPackages();
        $composer = $this->composerPackages();

        return new Stack(
            css: $this->cssFramework($npm),
            hasLivewire: isset($composer['livewire/livewire']),
            hasAuth: Route::has('login'),
            hasFilament: isset($composer['filament/filament']),
            hasMcp: isset($composer['laravel/mcp']),
            isFreshApp: $this->isFreshApp(),
            layouts: $this->layouts(),
            viteEntries: $this->viteEntries(),
            registersRoutesAfterWeb: $this->registersRoutesAfterWeb(),
        );
    }

    /**
     * @param  array<string, string>  $npm
     */
    private function cssFramework(array $npm): string
    {
        if (isset($npm['tailwindcss']) || isset($npm['@tailwindcss/vite'])) {
            return 'tailwind';
        }

        if (isset($npm['bootstrap'])) {
            return 'bootstrap';
        }

        return 'plain';
    }

    /**
     * A new application still routes "/" to the stock welcome page and has
     * no other web routes of its own.
     */
    private function isFreshApp(): bool
    {
        $routes = base_path('routes/web.php');

        if (! $this->files->exists($routes)) {
            return true;
        }

        $routeCount = preg_match_all('/Route::/', $this->files->get($routes));

        return $routeCount <= 1 && str_contains($this->files->get($routes), "view('welcome')");
    }

    /**
     * Layouts the public pages could render inside: anonymous component
     * layouts (resources/views/components/layouts) and @yield-based ones
     * (resources/views/layouts).
     *
     * @return array<int, string>
     */
    private function layouts(): array
    {
        $layouts = [];

        foreach ($this->files->glob(resource_path('views/components/layouts/*.blade.php')) as $path) {
            if ($this->isPublicLayout($path)) {
                $layouts[] = 'component:layouts.'.basename($path, '.blade.php');
            }
        }

        foreach ($this->files->glob(resource_path('views/layouts/*.blade.php')) as $path) {
            if ($this->isPublicLayout($path) && str_contains($this->files->get($path), '@yield(')) {
                $layouts[] = 'extends:layouts.'.basename($path, '.blade.php');
            }
        }

        return $layouts;
    }

    /**
     * A layout that reads the signed-in user (an app dashboard layout) would
     * fail for visitors, so it is not offered for public pages.
     */
    private function isPublicLayout(string $path): bool
    {
        $source = $this->files->get($path);

        return ! str_contains($source, 'auth()->user()') && ! str_contains($source, 'Auth::user()');
    }

    /**
     * Routes added through withRouting(then: ...) or Folio's pages: are
     * registered after routes/web.php, so Inlay's catch-all would hide them.
     */
    private function registersRoutesAfterWeb(): bool
    {
        $bootstrap = base_path('bootstrap/app.php');

        if (! $this->files->exists($bootstrap)) {
            return false;
        }

        return preg_match('/\b(then|pages)\s*:/', $this->files->get($bootstrap)) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function viteEntries(): array
    {
        if (! $this->files->exists(base_path('vite.config.js')) && ! $this->files->exists(base_path('vite.config.ts'))) {
            return [];
        }

        $entries = [];

        foreach (['resources/css/app.css', 'resources/sass/app.scss', 'resources/js/app.js', 'resources/js/app.ts'] as $entry) {
            if ($this->files->exists(base_path($entry))) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    private function npmPackages(): array
    {
        $manifest = $this->readJson(base_path('package.json'));

        return [...($manifest['dependencies'] ?? []), ...($manifest['devDependencies'] ?? [])];
    }

    /**
     * @return array<string, string>
     */
    private function composerPackages(): array
    {
        $manifest = $this->readJson(base_path('composer.json'));

        return [...($manifest['require'] ?? []), ...($manifest['require-dev'] ?? [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! $this->files->exists($path)) {
            return [];
        }

        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
