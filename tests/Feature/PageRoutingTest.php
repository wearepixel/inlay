<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

it('resolves a published page at its stored path under a configured prefix', function () {
    config(['inlay.blocks' => ['test-hero' => TestHeroSection::class]]);
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    $page = Page::create(['title' => 'About', 'path' => 'about', 'status' => PageStatus::Published]);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'About us']]);
    app(PublishPage::class)->execute($page);

    $this->get('/pages/about')->assertOk()->assertSee('About us');
});

it('resolves a nested path', function () {
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    Page::create(['title' => 'Team', 'path' => 'about/team', 'status' => PageStatus::Published]);

    $this->get('/pages/about/team')->assertOk();
});

it('404s for a draft page', function () {
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    Page::create(['title' => 'About', 'path' => 'about', 'status' => PageStatus::Draft]);

    $this->get('/pages/about')->assertNotFound();
});

it('404s for a page with no public path that is not the homepage', function () {
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    Page::create(['title' => 'Hidden', 'status' => PageStatus::Published]);

    $this->get('/pages/hidden')->assertNotFound();
});

it('404s for a path no page has, including when pages exist', function () {
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    Page::create(['title' => 'About', 'path' => 'about', 'status' => PageStatus::Published]);

    $this->get('/pages/does-not-exist')->assertNotFound();
});

it('resolves the homepage at the root when no prefix is configured', function () {
    Inlay::pageRoutes(view: 'public-page-view');

    Page::create(['title' => 'Home', 'is_homepage' => true, 'status' => PageStatus::Published]);

    $this->get('/')->assertOk();
});

it('resolves the prefix root and the prefix root with a trailing slash identically to the homepage', function () {
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    Page::create(['title' => 'Home', 'is_homepage' => true, 'status' => PageStatus::Published]);

    $this->get('/pages')->assertOk();
    $this->get('/pages/')->assertOk();
});

it('resolves the same homepage deterministically when malformed data has more than one', function () {
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    // Bypasses SetPageAsHomepage on purpose - simulating already-malformed
    // data (e.g. from a residual race SetPageAsHomepage's own docblock
    // documents), not something that action would ever itself produce.
    $older = Page::create(['title' => 'Older Home', 'status' => PageStatus::Published]);
    $older->forceFill(['is_homepage' => true])->save();
    $newer = Page::create(['title' => 'Newer Home', 'status' => PageStatus::Published]);
    $newer->forceFill(['is_homepage' => true])->save();

    expect(Page::query()->homepage()->count())->toBe(2);

    $this->get('/pages')->assertOk()->assertSee('Older Home')->assertDontSee('Newer Home');
    $this->get('/pages')->assertOk()->assertSee('Older Home');
});

it('404s for a draft homepage', function () {
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    Page::create(['title' => 'Home', 'is_homepage' => true, 'status' => PageStatus::Draft]);

    $this->get('/pages')->assertNotFound();
});

it('normalises pages, /pages, pages/, and /pages/ to the same prefix', function () {
    foreach (['pages', '/pages', 'pages/', '/pages/'] as $prefixForm) {
        $inlay = new InlayManager;
        $inlay->pageRoutes(prefix: $prefixForm, view: 'public-page-view');

        $page = new Page(['path' => 'about']);

        expect($inlay->url($page))->toBe('/pages/about');
    }
});

it('throws a clear exception when the configured public page view does not exist', function () {
    $this->withoutExceptionHandling();

    Inlay::pageRoutes(prefix: 'pages', view: 'view.that.does.not.exist');

    Page::create(['title' => 'About', 'path' => 'about', 'status' => PageStatus::Published]);

    $this->get('/pages/about');
})->throws(RuntimeException::class, 'could not find the public Page view');

it('registers both admin and public page routes via Inlay::routes()', function () {
    config(['inlay.pages' => ['prefix' => 'pages', 'view' => 'public-page-view', 'middleware' => ['web']]]);

    $inlay = new InlayManager;
    $inlay->routes();

    expect(Route::has('inlay.admin.pages.index'))->toBeTrue()
        ->and(Route::has('inlay.pages.show'))->toBeTrue();
});

it('is a harmless no-op when pageRoutes() is called twice with equivalent configuration', function () {
    $inlay = new InlayManager;

    $inlay->pageRoutes(prefix: 'pages', view: 'public-page-view');
    $inlay->pageRoutes(prefix: 'pages', view: 'public-page-view');

    expect(Route::has('inlay.pages.show'))->toBeTrue();
});

it('rejects a second pageRoutes() registration with incompatible configuration', function () {
    $inlay = new InlayManager;

    $inlay->pageRoutes(prefix: 'pages');
    $inlay->pageRoutes(prefix: 'content');
})->throws(RuntimeException::class, 'already been registered');

it('generates the URL for a registered prefix from a nested path, without storing anything extra on the Page', function () {
    $page = Page::create(['title' => 'Test', 'path' => 'test/test']);

    $inlay = new InlayManager;
    $inlay->pageRoutes(prefix: 'pages', view: 'public-page-view');

    expect($inlay->url($page))->toBe('/pages/test/test')
        ->and($page->fresh()->path)->toBe('test/test');
});

it('generates URLs from config alone, reflecting a prefix change immediately, when pageRoutes() has not been registered on this instance', function () {
    // Simulates a process that never called pageRoutes() itself (a queue
    // worker, a console command) - Inlay::url() still has to produce a
    // useful URL for display, generated from config rather than coupled to
    // route-registration state that may belong to a different bootstrap
    // path entirely (see InlayManager::url()'s docblock).
    $page = Page::create(['title' => 'Test', 'path' => 'test/test']);

    config(['inlay.pages' => ['prefix' => 'pages', 'view' => 'public-page-view', 'middleware' => ['web']]]);
    expect((new InlayManager)->url($page))->toBe('/pages/test/test');

    config(['inlay.pages' => ['prefix' => 'content', 'view' => 'public-page-view', 'middleware' => ['web']]]);
    expect((new InlayManager)->url($page))->toBe('/content/test/test');

    expect($page->fresh()->path)->toBe('test/test');
});

it('returns null from Inlay::url() for a non-homepage page with no public path', function () {
    $page = Page::create(['title' => 'Hidden']);

    expect(Inlay::url($page))->toBeNull();
});

it('generates the homepage URL as the route-group root', function () {
    $page = Page::create(['title' => 'Home', 'is_homepage' => true]);

    $inlay = new InlayManager;
    $inlay->pageRoutes(prefix: 'pages', view: 'public-page-view');

    expect($inlay->url($page))->toBe('/pages');
});

it('keeps public route middleware separate from the admin route middleware', function () {
    config(['inlay.pages' => ['prefix' => 'pages', 'view' => 'public-page-view', 'middleware' => ['web']]]);
    config(['inlay.admin' => ['prefix' => 'inlay', 'middleware' => ['web', 'auth']]]);

    $inlay = new InlayManager;
    $inlay->routes();

    Page::create(['title' => 'About', 'path' => 'about', 'status' => PageStatus::Published]);

    // The public route has no 'auth' middleware even though the admin does -
    // a visitor never needs an admin session to view a published Page.
    $this->get('/pages/about')->assertOk();
});
