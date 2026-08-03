<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;

it('registers the packaged admin routes under the configured prefix and name', function () {
    expect(route('inlay.admin.pages.index'))->toContain('/inlay/pages')
        ->and(route('inlay.admin.pages.create'))->toContain('/inlay/pages/create');
});

it('serves the page index over HTTP', function () {
    $this->get(route('inlay.admin.pages.index'))->assertOk()->assertSee('Pages');
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
