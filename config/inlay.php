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

        /*
        | 'ability' - a Gate ability every admin screen checks for itself, on
        | the first load and on every Livewire action after it, receiving the
        | Page where there is one. Unlike the middleware above, it still
        | applies when a host mounts Inlay's components on its own routes.
        | null leaves access to the middleware alone.
        |
        | 'layout' - a Blade component name (e.g. 'layouts.admin') to render
        | the admin inside instead of Inlay's own shell. It receives $slot and
        | $title. null uses Inlay's own layout.
        */
        'ability' => null,
        'layout' => null,
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

    /*
    |--------------------------------------------------------------------------
    | Preview links
    |--------------------------------------------------------------------------
    |
    | Applied when the host calls Inlay::previewLinkRoutes(). A preview link
    | is a signed, expiring URL that renders a draft (or a proposed, unsaved
    | composition) through Inlay::previewView() without an admin session -
    | the MCP tools hand them to agents, people and the screenshot driver.
    | 'ttl' is in minutes.
    |
    */
    'preview_links' => [
        'prefix' => 'inlay-preview',
        'middleware' => ['web'],
        'ttl' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP
    |--------------------------------------------------------------------------
    |
    | Used by Inlay's MCP tools (requires laravel/mcp). Every tool checks
    | 'abilities.manage' for the MCP request's user, passing the Page where
    | there is one, so a Gate or policy can scope access per page. Publishing
    | also needs 'abilities.publish' AND 'allow_publish' - it is off by
    | default because a published page is immediately public.
    |
    | 'screenshots.driver' - null (no screenshots) or 'browsershot'
    | (spatie/browsershot). Bind WeArePixel\Inlay\Contracts\Screenshotter
    | yourself to use any other renderer. The renderer loads a signed
    | preview link, so it must be able to reach this application's URL.
    |
    */
    'mcp' => [
        'abilities' => [
            'manage' => 'inlay.manage-pages',
            'publish' => 'inlay.publish-pages',
            'author' => 'inlay.author-blocks',
        ],
        'allow_publish' => false,
        'writes_per_minute' => 60,
        'max_image_kilobytes' => 5120,
        'max_image_pixels' => 40_000_000,
        'screenshots' => [
            'driver' => env('INLAY_SCREENSHOT_DRIVER'),
            'widths' => ['desktop' => 1440, 'mobile' => 390],
            'per_minute' => 6,
            'max_height' => 7500,
            'browsershot' => [
                'chrome_path' => env('INLAY_CHROME_PATH'),
                'node_modules_path' => env('INLAY_NODE_MODULES_PATH'),
                'no_sandbox' => (bool) env('INLAY_CHROME_NO_SANDBOX', false),
                'timeout' => 60,
                'quality' => 70,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Styling and agent-authored blocks
    |--------------------------------------------------------------------------
    |
    | 'framework' - the CSS approach this site uses (tailwind, bootstrap or
    | plain). Agents are told to use it, and the starter kit defaults to it.
    |
    | Agent-authored blocks may only use classes the site's compiled CSS
    | actually defines - read from the Vite manifest ('vite_manifest', null
    | for public/build/manifest.json) plus any 'stylesheets'. Rebuild your
    | assets to make new classes available; 'extra_classes' allows classes
    | that exist only for JavaScript hooks.
    |
    | 'agent_blocks.enabled' - lets MCP users with the 'author' ability create
    | blocks (run the migrations first). Off by default.
    |
    */
    'styling' => [
        'framework' => env('INLAY_CSS_FRAMEWORK', 'tailwind'),
        'vite_manifest' => null,
        'vite_entries' => null,
        'stylesheets' => [],
        'extra_classes' => [],
        'denied_classes' => ['fixed'],
    ],

    'agent_blocks' => [
        'enabled' => (bool) env('INLAY_AGENT_BLOCKS', false),
    ],
];
