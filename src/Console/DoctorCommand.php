<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\RouteConflicts;
use WeArePixel\Inlay\Support\LivewireRequirement;
use WeArePixel\Inlay\Support\SectionRegistry;

/**
 * Checks an installation and says exactly what to do about anything
 * missing. Exits with a failure code when something would stop editors
 * from working, so it can run in CI.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'inlay:doctor';

    protected $description = 'Check that Inlay is set up correctly and explain how to fix anything that is not';

    private const PASS = 'pass';

    private const WARN = 'warn';

    private const FAIL = 'fail';

    private int $failures = 0;

    public function handle(InlayManager $inlay, SectionRegistry $sections, RouteConflicts $conflicts): int
    {
        $this->components->info('Checking Inlay');

        $this->check('Config published', file_exists(config_path('inlay.php')) ? self::PASS : self::WARN,
            'Run php artisan vendor:publish --tag=inlay-config to customise it.');

        $this->check('Database tables', $this->tablesExist() ? self::PASS : self::FAIL,
            'Run php artisan migrate.');

        $this->check('Livewire installed (for the admin)', LivewireRequirement::isSatisfied() ? self::PASS : self::FAIL,
            'Run composer require livewire/livewire.');

        $this->check('Admin routes registered', Route::has('inlay.admin.pages.index') ? self::PASS : self::FAIL,
            'Add Inlay::adminRoutes(); to routes/web.php.');

        $this->check('Admin access restricted', $this->adminIsGuarded(),
            "Set config('inlay.admin.ability') and define that Gate ability, or add 'auth' to config('inlay.admin.middleware').");

        $this->check('Sections registered', $sections->slugs() !== [] ? self::PASS : self::FAIL,
            'Add sections to resources/views/inlay/sections and call Inlay::singleFileSections(...) in a service provider.');

        $this->check('Preview uses your frontend', $this->previewViewExists($inlay) ? self::PASS : self::WARN,
            'Call Inlay::previewView(\'inlay.pages.show\') in a service provider so the editor preview looks like the live site.');

        $this->check('Public page routes registered', Route::has('inlay.pages.show') ? self::PASS : self::WARN,
            'Add Inlay::pageRoutes(); as the last line of routes/web.php, or render pages from your own routes with <x-inlay::render>.');

        $this->checkRouteOrder($inlay, $conflicts);

        $this->check('Published homepage', $this->hasPublishedHomepage() ? self::PASS : self::WARN,
            'Create a page in the admin, mark it as the homepage and publish it.');

        $this->check('Uploaded images are public', $this->storageIsLinked() ? self::PASS : self::WARN,
            'Run php artisan storage:link.');

        $this->check('Preview links can be shared', config('cache.default') !== 'array' ? self::PASS : self::WARN,
            'Preview links need a cache store shared between requests - set CACHE_STORE to database, file or redis.');

        $this->newLine();

        if ($this->failures > 0) {
            $this->components->error("{$this->failures} problem(s) will stop editors from working.");

            return self::FAILURE;
        }

        $this->components->info('Inlay is ready.');

        return self::SUCCESS;
    }

    private function check(string $label, string $status, string $fix): void
    {
        $badge = match ($status) {
            self::PASS => '<fg=green;options=bold>OK</>',
            self::WARN => '<fg=yellow;options=bold>CHECK</>',
            default => '<fg=red;options=bold>FIX</>',
        };

        $this->components->twoColumnDetail($label, $badge);

        if ($status !== self::PASS) {
            $this->line("  <fg=gray>-></> {$fix}");
        }

        if ($status === self::FAIL) {
            $this->failures++;
        }
    }

    /**
     * Inlay's page route answers any URL, so it has to be registered after
     * every other route - otherwise an app route hides a page, or the page
     * route hides an app route.
     */
    private function checkRouteOrder(InlayManager $inlay, RouteConflicts $conflicts): void
    {
        if (! Route::has(RouteConflicts::PAGE_ROUTE)) {
            return;
        }

        $hiddenPages = [];

        foreach ($this->pagesWithUrls($inlay) as $title => $url) {
            $takenBy = $conflicts->routeTaking($url);

            if ($takenBy !== null) {
                $hiddenPages[] = "{$title} ({$url}) is answered by {$takenBy}";
            }
        }

        $this->check('Pages are reachable at their URLs', $hiddenPages === [] ? self::PASS : self::FAIL,
            implode("\n     ", [...$hiddenPages, 'Remove or rename those routes, or give the pages other paths.']));

        $hiddenRoutes = $conflicts->routesHiddenByPages();

        $this->check('Inlay::pageRoutes() registered last', $hiddenRoutes === [] ? self::PASS : self::FAIL,
            'It is registered before '.implode(', ', $hiddenRoutes).', so those never run. Move Inlay::pageRoutes() to the end of routes/web.php.');
    }

    /**
     * @return array<string, string> page title => URL
     */
    private function pagesWithUrls(InlayManager $inlay): array
    {
        if (! Schema::hasTable('pages')) {
            return [];
        }

        $urls = [];

        foreach (Page::query()->orderBy('title')->get() as $page) {
            $url = $inlay->url($page);

            if ($url !== null) {
                $urls[$page->title] = $url;
            }
        }

        return $urls;
    }

    private function tablesExist(): bool
    {
        foreach (['pages', 'page_sections', 'page_revisions', 'inlay_site_settings'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function adminIsGuarded(): string
    {
        $ability = config('inlay.admin.ability');

        if (is_string($ability) && $ability !== '') {
            return Gate::has($ability) ? self::PASS : self::FAIL;
        }

        $middleware = (array) config('inlay.admin.middleware', []);

        return in_array('auth', $middleware, true) ? self::WARN : self::FAIL;
    }

    private function previewViewExists(InlayManager $inlay): bool
    {
        $view = $inlay->resolvePreviewView();

        if ($view === null) {
            return false;
        }

        try {
            view()->getFinder()->find($view);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    private function hasPublishedHomepage(): bool
    {
        if (! Schema::hasTable('pages')) {
            return false;
        }

        return Page::query()->where('is_homepage', true)->whereHas('revisions')->exists();
    }

    private function storageIsLinked(): bool
    {
        return config('inlay.disk', 'public') !== 'public' || file_exists(public_path('storage'));
    }
}
