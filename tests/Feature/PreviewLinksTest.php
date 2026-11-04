<?php

declare(strict_types=1);

use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Preview\PreviewLinks;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => ['test-hero' => TestHeroSection::class]]);
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
    $url = Illuminate\Support\Facades\URL::temporarySignedRoute('inlay.preview-link', now()->addMinutes(5), ['token' => str_repeat('a', 40)]);

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
