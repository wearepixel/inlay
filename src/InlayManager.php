<?php

declare(strict_types=1);

namespace WeArePixel\Inlay;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use RuntimeException;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinition;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\SingleFileLoader;
use WeArePixel\Inlay\SiteSettings\SiteSettingsRegistry;
use WeArePixel\Inlay\Support\LivewireRequirement;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;

/**
 * The single developer-facing entry point for wiring Inlay into a host
 * application: registering the packaged admin routes, and telling the
 * preview iframe which host-owned view renders a composition using the
 * application's real frontend - docs/v1-implementation-specification.md §18.
 */
final class InlayManager
{
    private ?string $previewView = null;

    /** @var (Closure(string, mixed...): ?string)|null */
    private ?Closure $adminUrlResolver = null;

    private bool $pageRoutesRegistered = false;

    private ?string $activePagesPrefix = null;

    private ?string $activePagesView = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $activePagesMiddleware = null;

    /**
     * Registers both the packaged admin routes and Inlay-owned public Page
     * routes - a convenience for a host that wants both with no
     * per-group configuration. Equivalent to calling adminRoutes() and
     * pageRoutes() separately, which is what most hosts documented in
     * docs/inlay/v1/routing actually want (pageRoutes() alone, or not at all,
     * favouring their own application-owned routes instead).
     */
    public function routes(): void
    {
        $this->adminRoutes();
        $this->pageRoutes();
    }

    /**
     * Registers the packaged admin routes (page index, create, editor,
     * preview) under config('inlay.admin.prefix') / config('inlay.admin.middleware').
     * Called explicitly by the host application, e.g. in routes/web.php,
     * after its own routes - the package registers nothing on its own.
     */
    public function adminRoutes(): void
    {
        LivewireRequirement::ensureInstalled();

        $middleware = (array) config('inlay.admin.middleware', ['web']);

        // Every admin action after the first page load (add/reorder/delete a
        // section, persist a setting, publish) is dispatched to Livewire's
        // own update endpoint, not back through this route group - so
        // config('inlay.middleware') would otherwise only ever guard the
        // very first GET request. Registering it as Livewire's "persistent"
        // middleware makes it re-run on every subsequent action too. Group
        // names and aliases (e.g. 'web', 'auth') must be resolved to their
        // concrete class strings first: Livewire compares by exact
        // class-string match against the route's own already-resolved
        // middleware, not by alias name.
        //
        // The "web" group itself must NOT be included here. Livewire's own
        // update endpoint already runs the real "web" group exactly once, on
        // the actual incoming request - that's what decrypts the session
        // cookie and starts the session correctly. Persistent middleware
        // re-runs its resolved classes a second time against a *cloned*
        // request built from the original page's route. Re-running
        // EncryptCookies on that clone tries to decrypt a cookie value the
        // first pass already decrypted, fails, and silently nulls it out -
        // StartSession then opens a brand new anonymous session and
        // Authenticate correctly (from its own perspective) redirects to
        // login, logging out an otherwise perfectly valid session on the
        // very next admin action. Only middleware beyond the base "web"
        // group (auth, authorization, etc.) needs to persist.
        $persistent = array_values(array_diff($middleware, ['web']));

        Livewire::addPersistentMiddleware(
            Route::resolveMiddleware($persistent)
        );

        Route::group([
            'prefix' => config('inlay.admin.prefix', 'inlay'),
            'as' => 'inlay.admin.',
            'middleware' => $middleware,
        ], __DIR__.'/../routes/admin.php');

        // See the matching call in pageRoutes() for why this is needed -
        // makes route('inlay.admin.*') reliable immediately, not just once
        // an actual request has been dispatched.
        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * Registers Inlay-owned public routing for published Pages - a single
     * route resolving any path against config('inlay.pages.view') (or the
     * $view argument), rendering it as a real Blade view your application
     * owns entirely (docs/inlay/v1/routing). Inlay never registers this
     * automatically; a host must opt in explicitly, and a root-level
     * (unprefixed) registration is a genuine catch-all that must be
     * registered last, after the host's own routes.
     *
     * Method arguments override config('inlay.pages.*'), which overrides
     * the package's own defaults. 'pages', '/pages', 'pages/', and
     * '/pages/' are all normalised to the same internal prefix value.
     *
     * Calling this twice in the same process with equivalent resolved
     * configuration is a harmless no-op (the second call is ignored);
     * calling it twice with genuinely different configuration throws,
     * since only one public Page route registration can be "the" active
     * one for Inlay::url() to generate against.
     *
     * @param  array<int, string>|null  $middleware
     */
    public function pageRoutes(?string $prefix = null, ?string $view = null, ?array $middleware = null): void
    {
        $resolvedPrefix = self::normalizePrefix($prefix ?? config('inlay.pages.prefix'));
        $resolvedView = $view ?? config('inlay.pages.view', 'inlay.pages.show');
        $resolvedMiddleware = $middleware ?? (array) config('inlay.pages.middleware', ['web']);

        if ($this->pageRoutesRegistered) {
            if ($this->activePagesPrefix === $resolvedPrefix
                && $this->activePagesView === $resolvedView
                && $this->activePagesMiddleware === $resolvedMiddleware) {
                return;
            }

            throw new RuntimeException(
                'Inlay::pageRoutes() has already been registered in this process with different configuration. '
                .'Registering it more than once with incompatible prefix/view/middleware is not supported - '
                .'call it exactly once, e.g. from your application\'s routes/web.php.'
            );
        }

        $this->pageRoutesRegistered = true;
        $this->activePagesPrefix = $resolvedPrefix;
        $this->activePagesView = $resolvedView;
        $this->activePagesMiddleware = $resolvedMiddleware;

        Route::group([
            'prefix' => $resolvedPrefix,
            'as' => 'inlay.pages.',
            'middleware' => $resolvedMiddleware,
        ], __DIR__.'/../routes/pages.php');

        // Laravel's route name lookup is a cache populated when a route is
        // first added to the collection - at that exact moment, a group's
        // 'as' prefix is already merged in, but this route's own ->name()
        // call (inside routes/pages.php) hasn't run yet, so the cache would
        // otherwise hold "inlay.pages." (no "show") until something else
        // happens to rebuild it (Laravel does this itself the first time it
        // matches an incoming request against the route collection, which
        // is why this is invisible in a normal request lifecycle - pages.php
        // is required by the host's own routes/web.php, long before any
        // request is dispatched). Forcing it here makes route('inlay.pages.
        // show') and Inlay::url() reliable immediately, including from a
        // console command or test that never dispatches a real request.
        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * Registers the signed preview-link route (Inlay\Preview\PreviewLinks).
     * It needs no admin session - the signature and an expiring token are
     * the access control - so it must not sit behind the admin middleware.
     */
    public function previewLinkRoutes(): void
    {
        if (Route::has('inlay.preview-link')) {
            return;
        }

        Route::group([
            'prefix' => trim((string) config('inlay.preview_links.prefix', 'inlay-preview'), '/'),
            'as' => 'inlay.',
            'middleware' => (array) config('inlay.preview_links.middleware', ['web']),
        ], __DIR__.'/../routes/preview-links.php');

        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * The host-owned Blade view PagesController renders a public Page
     * through - whatever pageRoutes() was actually registered with (which
     * may have overridden config('inlay.pages.view') via its own $view
     * argument), falling back to config directly if pageRoutes() hasn't
     * run yet in this process.
     */
    public function resolvePagesView(): string
    {
        return $this->activePagesView ?? config('inlay.pages.view', 'inlay.pages.show');
    }

    /**
     * Normalises a configured public-route prefix so 'pages', '/pages',
     * 'pages/', and '/pages/' all behave identically, and an all-slash or
     * empty value means "no prefix" (root-level routing) rather than a
     * literal empty path segment.
     */
    private static function normalizePrefix(?string $prefix): ?string
    {
        if ($prefix === null) {
            return null;
        }

        $trimmed = trim($prefix, "/ \t\n\r\0\x0B");

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * The public URL for a Page, generated from live configuration rather
     * than the database - a Page row never stores a route prefix, so
     * changing config('inlay.pages.prefix') (or the prefix argument to a
     * fresh pageRoutes() call) changes every Page's URL immediately,
     * without touching a single Page row (docs/inlay/v1/routing).
     *
     * Uses the real named route when pageRoutes() has been registered in
     * this process (so nested paths generate correctly through Laravel's
     * own URL generator); otherwise falls back to building the URL
     * manually from config, so URL display inside the admin stays useful
     * even when route registration happens in a different application
     * bootstrap path than the one currently running (e.g. a queued job, a
     * console command) - see the InlayManager class docblock.
     *
     * Returns null for a non-homepage Page with no public path - it has
     * no Inlay-owned public URL, by design (it's host-rendered only).
     */
    public function url(Page $page): ?string
    {
        if (! $page->is_homepage && $page->path === null) {
            return null;
        }

        $path = $page->is_homepage ? null : $page->path;

        if ($this->pageRoutesRegistered && Route::has('inlay.pages.show')) {
            return route('inlay.pages.show', $path === null ? [] : ['path' => $path], absolute: false);
        }

        $prefix = $this->pageRoutesRegistered
            ? $this->activePagesPrefix
            : self::normalizePrefix(config('inlay.pages.prefix'));

        $segments = array_filter([$prefix, $path], fn (?string $segment): bool => $segment !== null && $segment !== '');

        return '/'.implode('/', $segments);
    }

    /**
     * Registers the host-owned view that renders a composed page's HTML
     * inside the host application's own layout for the admin preview
     * iframe. Receives $page, $composition (a Htmlable), and $context.
     * With no view configured, the preview falls back to a bare, clearly
     * labeled unstyled wrapper rather than silently pretending to be real.
     */
    public function previewView(string $view): void
    {
        $this->previewView = $view;
    }

    /**
     * @return view-string|null
     */
    public function resolvePreviewView(): ?string
    {
        return $this->previewView;
    }

    /**
     * Renders a Page's latest published revision as a genuine Htmlable
     * composition - the same primitive both the public PagesController and
     * <x-inlay::render> use internally, also directly usable by a host
     * application that wants to embed a Page inside a route it already
     * owns (docs/inlay/v1/routing). A Page with no revisions yet (never
     * published) renders as empty, exactly like an empty section list -
     * there is no "draft preview" meaning here, that's what
     * Inlay::previewView() is for.
     *
     * @param  array<string, mixed>|null  $context
     */
    public function renderPage(Page $page, ?array $context = null): Htmlable
    {
        $revision = $page->revisions()->first();

        $sections = $revision?->snapshot['sections'] ?? [];

        return new HtmlString(app(SectionRenderer::class)->render($sections, $context));
    }

    /**
     * Registers a Page Definition - developer-owned composition policy for
     * a kind of Page (which Sections it may contain, which are required or
     * locked, what a new Page of this kind starts with). See
     * docs/inlay/v1/page-definitions. Every Section reference inside it is
     * validated immediately against the currently-registered Sections, so
     * register Page Definitions after registering Sections.
     *
     * @param  class-string<PageDefinition>  $definitionClass
     */
    public function pageDefinition(string $definitionClass): void
    {
        app(PageDefinitionRegistry::class)->register($definitionClass);
    }

    /**
     * @param  array<int, class-string<PageDefinition>>  $definitionClasses
     */
    public function pageDefinitions(array $definitionClasses): void
    {
        foreach ($definitionClasses as $definitionClass) {
            $this->pageDefinition($definitionClass);
        }
    }

    /**
     * Registers a single Blade file as an Inlay section - a second,
     * class-free authoring style compiling into the exact same
     * Definition a class + view section resolves to. See
     * docs/inlay/v1/single-file-sections for the authoring syntax.
     *
     * @param  string  $path  absolute path to the section's Blade file
     */
    public function singleFileSection(string $path): void
    {
        app(SectionRegistry::class)->registerSingleFile(
            app(SingleFileLoader::class)->load($path)
        );
    }

    /**
     * Registers every *.blade.php file directly inside a directory as a
     * single-file section, each using its own declared handle (from
     * section(Section::make($handle)...) inside the file) rather than
     * its filename - the filename is just a developer-organisation
     * convenience, and deriving the handle from it as well would leave two
     * possible sources of truth for the same thing.
     */
    public function singleFileSections(string $directory): void
    {
        foreach (glob(rtrim($directory, '/').'/*.blade.php') ?: [] as $path) {
            $this->singleFileSection($path);
        }
    }

    /**
     * Aborts with a 403 unless the current user has
     * config('inlay.admin.ability'). Does nothing when no ability is set.
     */
    public function authorizeAdmin(?Page $page = null): void
    {
        $ability = config('inlay.admin.ability');

        if (! is_string($ability) || $ability === '') {
            return;
        }

        abort_unless(Gate::allows($ability, $page === null ? [] : [$page]), 403);
    }

    /**
     * Points the admin's own links (back to the page list, into the editor,
     * to site settings) at a host's routes when it mounts Inlay's components
     * itself. The resolver receives the screen ('pages.index',
     * 'pages.create', 'pages.edit', 'settings') and its parameter (a Page,
     * or a settings group handle), and returns null to keep Inlay's route.
     *
     * @param  (Closure(string, mixed...): ?string)|null  $resolver
     */
    public function adminUrlsUsing(?Closure $resolver): void
    {
        $this->adminUrlResolver = $resolver;
    }

    public function adminUrl(string $screen, mixed ...$parameters): string
    {
        $custom = $this->adminUrlResolver !== null
            ? ($this->adminUrlResolver)($screen, ...$parameters)
            : null;

        return $custom ?? route("inlay.admin.{$screen}", array_values(array_filter($parameters, fn (mixed $parameter) => $parameter !== null)));
    }

    /**
     * Registers a group of site-wide content (contact details, header
     * links) editable under Settings in the admin and read anywhere with
     * Inlay::setting('handle.field').
     */
    public function siteSettings(string $handle, Settings $schema, ?string $label = null): void
    {
        app(SiteSettingsRegistry::class)->register($handle, $schema, $label);
    }

    /**
     * A site setting by "group.field", hydrated like a section setting.
     * Nested values can be reached with more dots, e.g. "header.cta.url".
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return app(SiteSettingsRegistry::class)->value($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(string $handle): array
    {
        return app(SiteSettingsRegistry::class)->values($handle);
    }

    /** Cache-busting token for a resources/dist asset, keyed to its own mtime. */
    public static function assetVersion(string $filename): string
    {
        $path = __DIR__.'/../resources/dist/'.$filename;

        $mtime = @filemtime($path);

        return $mtime !== false ? (string) $mtime : '0';
    }
}
