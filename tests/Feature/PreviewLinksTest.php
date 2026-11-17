<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Preview\PreviewLinks;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => ['test-hero' => TestHeroSection::class]]);
    Inlay::previewView('host-preview');
    Inlay::previewLinkRoutes();

    $this->page = Page::create(['title' => 'Spring sale', 'path' => 'spring']);
    $this->page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Stored draft']]);
});

it('renders the current draft through the host preview view without an admin session', function () {
    $url = app(PreviewLinks::class)->forPage($this->page);

    $this->get($url)
        ->assertOk()
        ->assertSee('host-frame-marker', false)
        ->assertSee('Stored draft')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('renders a proposed composition without saving it', function () {
    $url = app(PreviewLinks::class)->forComposition($this->page, [
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Proposed heading']],
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Proposed heading')
        ->assertDontSee('Stored draft');

    expect($this->page->sections()->first()->settings)->toBe(['heading' => 'Stored draft']);
});

it('refuses a link whose signature has been tampered with', function () {
    $url = app(PreviewLinks::class)->forPage($this->page);

    $this->get(preg_replace('/signature=[^&]+/', 'signature=forged', $url))
        ->assertForbidden()
        ->assertDontSee('Stored draft');
});

it('refuses a token that was never issued, even when correctly signed', function () {
    $url = URL::temporarySignedRoute('inlay.preview-link', now()->addMinutes(5), ['token' => str_repeat('a', 40)], absolute: false);

    $this->get($url)->assertNotFound();
});

it('stops working once the link expires', function () {
    config(['inlay.preview_links.ttl' => 30]);
    $url = app(PreviewLinks::class)->forPage($this->page);

    $this->travel(31)->minutes();

    $this->get($url)
        ->assertForbidden()
        ->assertDontSee('Stored draft');
});

it('builds links from APP_URL, never from the host the request arrived on', function () {
    config(['app.url' => 'https://shop.example']);
    app('url')->forceRootUrl('http://attacker.example');

    $url = app(PreviewLinks::class)->forPage($this->page);

    expect($url)->toStartWith('https://shop.example/inlay-preview/');

    app('url')->forceRootUrl(null);
    $this->get($url)->assertOk()->assertSee('Stored draft');
});

it('sends no referrer so the token cannot leak through a clicked link', function () {
    $this->get(app(PreviewLinks::class)->forPage($this->page))
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('leaves the editor selection script out of a shared preview link', function () {
    $this->get(app(PreviewLinks::class)->forPage($this->page))
        ->assertOk()
        ->assertDontSee('data-inlay-section', false)
        ->assertDontSee('inlay:select-section', false);
});
