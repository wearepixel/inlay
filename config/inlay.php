<?php

declare(strict_types=1);

return [
    'blocks' => [],
    'disk' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Admin route prefix and middleware
    |--------------------------------------------------------------------------
    |
    | Applied when the host application calls Inlay::routes() from its own
    | routes/web.php. The package registers no routes, and enforces no
    | authentication or authorization, on its own - see
    | docs/v1-implementation-specification.md. Add your own guard here, e.g.
    | ['web', 'auth'] or ['web', 'auth', 'can:manage-pages'] - the default of
    | ['web'] leaves every admin action public. Inlay::routes() also
    | registers this same middleware as Livewire "persistent" middleware, so
    | it re-runs on every subsequent admin action (add/reorder/delete a
    | block, persist a setting, publish), not just the first page load.
    |
    */
    'route_prefix' => 'inlay',
    'middleware' => ['web'],
];
