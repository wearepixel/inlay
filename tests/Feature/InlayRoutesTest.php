<?php

declare(strict_types=1);

use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;

it('registers the packaged admin routes under the configured prefix and name', function () {
    expect(route('inlay.admin.pages.index'))->toContain('/inlay/pages')
        ->and(route('inlay.admin.pages.create'))->toContain('/inlay/pages/create');
});

it('serves the page index over HTTP', function () {
    $this->get(route('inlay.admin.pages.index'))->assertOk()->assertSee('Pages');
});

it('redirects a bare visit to the admin prefix root to the page index, rather than 404ing', function () {
    $this->get('/inlay')->assertRedirect(route('inlay.admin.pages.index'));
});

it('serves the create-page form over HTTP', function () {
    $this->get(route('inlay.admin.pages.create'))->assertOk()->assertSee('New page');
});

it('serves the page editor over HTTP for an existing page', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    $this->get(route('inlay.admin.pages.edit', $page))->assertOk()->assertSee('Home');
});

it('serves the self-hosted admin CSS with no CDN dependency', function () {
    $response = $this->get(route('inlay.admin.assets.css'))->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/css');
});

it('serves the self-hosted admin JS with no CDN dependency', function () {
    $this->get(route('inlay.admin.assets.js'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript');
});

it('cache-busts the admin CSS and JS with a version derived from each file\'s own modification time', function () {
    $html = $this->get(route('inlay.admin.pages.index'))->getContent();

    $cssVersion = InlayManager::assetVersion('inlay.css');
    $jsVersion = InlayManager::assetVersion('inlay.js');

    expect($html)
        ->toContain(route('inlay.admin.assets.css').'?v='.$cssVersion)
        ->toContain(route('inlay.admin.assets.js').'?v='.$jsVersion);
});

// Regression: wire:loading elements (Saving.../Publishing...) render permanently visible, not just while
// loading, when a host sets inject_assets=false (Nourishd's own convention) and the layout only calls
// @livewireScripts - the CSS that hides them by default only ships via @livewireStyles.
it('emits @livewireStyles even when the host has disabled Livewire\'s automatic asset injection', function () {
    config(['livewire.inject_assets' => false]);

    $html = $this->get(route('inlay.admin.pages.index'))->getContent();

    expect($html)->toContain('[wire\:loading]');
});
