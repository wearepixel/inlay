<?php

declare(strict_types=1);

return [
    'blocks' => [],
    'disk' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Admin routes
    |--------------------------------------------------------------------------
    |
    | Applied when the host application calls Inlay::adminRoutes() (or the
    | combined Inlay::routes()) from its own routes/web.php. The package
    | registers no routes, and enforces no authentication or authorization,
    | on its own - see docs/v1-implementation-specification.md. Add your own
    | guard here, e.g. ['web', 'auth'] or ['web', 'auth', 'can:manage-pages']
    | - the default of ['web'] leaves every admin action public.
    | Inlay::adminRoutes() also registers this same middleware as Livewire
    | "persistent" middleware, so it re-runs on every subsequent admin
    | action (add/reorder/delete a block, persist a setting, publish), not
    | just the first page load.
    |
    */
    'admin' => [
        'prefix' => 'inlay',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Page routes
    |--------------------------------------------------------------------------
    |
    | Applied when the host application calls Inlay::pageRoutes() (or the
    | combined Inlay::routes()). Entirely separate from the admin middleware
    | above - a public visitor never touches the admin's guard, and an admin
    | session is never required just to view a published Page.
    |
    | 'prefix' - null registers Pages at the application's root (a real
    | catch-all - register it last, after your own application's routes).
    | A string prefix (e.g. 'pages') scopes every Page under that segment
    | instead, which is both safer against route collisions and doesn't
    | need to be registered last. Method arguments passed to
    | Inlay::pageRoutes() override this value - see docs/inlay/v1/installation.
    |
    | 'view' - the host-owned Blade view that renders a Page on a real
    | visit. Receives $page, $composition, and $context, exactly like
    | Inlay::previewView() - see README.md#public-page-view.
    |
    */
    'pages' => [
        'prefix' => null,
        'middleware' => ['web'],
        'view' => 'inlay.pages.show',
    ],
];
