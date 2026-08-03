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

/**
 * Regression coverage: the CSS/JS routes are cached for an hour
 * (Cache-Control: max-age=3600 - see InlayAssetController), so a real
 * change to either file could sit invisible in an already-open browser
 * tab for up to an hour with no visible sign anything was wrong. The
 * layout stamps a "?v=" query string derived from each file's own
 * filemtime(), so a change to the file is a change to the URL - never a
 * stale cached response for a URL nobody requests anymore.
 */
it('cache-busts the admin CSS and JS with a version derived from each file\'s own modification time', function () {
    $html = $this->get(route('inlay.admin.pages.index'))->getContent();

    $cssVersion = InlayManager::assetVersion('inlay.css');
    $jsVersion = InlayManager::assetVersion('inlay.js');

    expect($html)
        ->toContain(route('inlay.admin.assets.css').'?v='.$cssVersion)
        ->toContain(route('inlay.admin.assets.js').'?v='.$jsVersion);
});
