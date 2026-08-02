<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use WearePixel\Inlay\Facades\Inlay;

it('registers config(\'inlay.middleware\') as Livewire persistent middleware, resolving aliases to concrete classes', function () {
    config(['inlay.middleware' => ['web', 'auth']]);

    Inlay::routes();

    $persisted = app(PersistentMiddleware::class)->getPersistentMiddleware();

    // Without this, config('inlay.middleware') would only ever guard the
    // very first GET request - every subsequent admin action (delete a
    // page, publish, persist a setting) is dispatched straight to
    // Livewire's own update endpoint and would bypass it entirely.
    expect($persisted)->toContain(Authenticate::class);
});
