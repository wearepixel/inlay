<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console\Install;

use Illuminate\Filesystem\Filesystem;

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
            hasAuth: $this->hasAuth($composer),
            hasFilament: isset($composer['filament/filament']),
            hasMcp: isset($composer['laravel/mcp']),
            isFreshApp: $this->isFreshApp(),
            layouts: $this->layouts(),
            viteEntries: $this->viteEntries(),
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
     * @param  array<string, string>  $composer
     */
    private function hasAuth(array $composer): bool
    {
        foreach (['laravel/fortify', 'laravel/breeze', 'laravel/jetstream', 'laravel/ui', 'filament/filament'] as $package) {
            if (isset($composer[$package])) {
                return true;
            }
        }

        return $this->files->exists(app_path('Http/Controllers/Auth'))
            || $this->files->isDirectory(resource_path('views/auth'))
            || $this->files->isDirectory(resource_path('views/livewire/auth'));
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
            $layouts[] = 'component:layouts.'.basename($path, '.blade.php');
        }

        foreach ($this->files->glob(resource_path('views/layouts/*.blade.php')) as $path) {
            if (str_contains($this->files->get($path), '@yield(')) {
                $layouts[] = 'extends:layouts.'.basename($path, '.blade.php');
            }
        }

        return $layouts;
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
