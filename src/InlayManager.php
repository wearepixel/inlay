<?php

declare(strict_types=1);

namespace WearePixel\Inlay;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use WearePixel\Inlay\SingleFile\ComponentLoader;
use WearePixel\Inlay\Support\SectionRegistry;

/**
 * The single developer-facing entry point for wiring Inlay into a host
 * application: registering the packaged admin routes, and telling the
 * preview iframe which host-owned view renders a composition using the
 * application's real frontend - docs/v1-implementation-specification.md §18.
 */
final class InlayManager
{
    private ?string $previewView = null;

    /**
     * Registers the packaged admin routes (page index, create, editor,
     * preview) under config('inlay.route_prefix') / config('inlay.middleware').
     * Called explicitly by the host application, e.g. in routes/web.php,
     * after its own routes - the package registers nothing on its own.
     */
    public function routes(): void
    {
        $middleware = (array) config('inlay.middleware', ['web']);

        // Every admin action after the first page load (add/reorder/delete a
        // component, persist a setting, publish) is dispatched to Livewire's
        // own update endpoint, not back through this route group - so
        // config('inlay.middleware') would otherwise only ever guard the
        // very first GET request. Registering it as Livewire's "persistent"
        // middleware makes it re-run on every subsequent action too. Group
        // names and aliases (e.g. 'web', 'auth') must be resolved to their
        // concrete class strings first: Livewire compares by exact
        // class-string match against the route's own already-resolved
        // middleware, not by alias name.
        Livewire::addPersistentMiddleware(
            Route::resolveMiddleware($middleware)
        );

        Route::group([
            'prefix' => config('inlay.route_prefix', 'inlay'),
            'as' => 'inlay.',
            'middleware' => $middleware,
        ], __DIR__.'/../routes/admin.php');
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

    public function resolvePreviewView(): ?string
    {
        return $this->previewView;
    }

    /**
     * Registers a single Blade file as an Inlay component - a second,
     * class-free authoring style compiling into the exact same
     * ComponentDefinition a class + view component resolves to. See
     * docs/components.md for the authoring syntax.
     *
     * @param  string  $path  absolute path to the component's Blade file
     */
    public function singleFileComponent(string $path): void
    {
        app(SectionRegistry::class)->registerSingleFile(
            app(ComponentLoader::class)->load($path)
        );
    }

    /**
     * Registers every *.blade.php file directly inside a directory as a
     * single-file component, each using its own declared handle (from
     * component(Component::make($handle)...) inside the file) rather than
     * its filename - the filename is just a developer-organisation
     * convenience, and deriving the handle from it as well would leave two
     * possible sources of truth for the same thing.
     */
    public function singleFileComponents(string $directory): void
    {
        foreach (glob(rtrim($directory, '/').'/*.blade.php') ?: [] as $path) {
            $this->singleFileComponent($path);
        }
    }
}
