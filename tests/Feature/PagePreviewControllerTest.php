<?php

declare(strict_types=1);

use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('falls back to a labeled unstyled wrapper when no previewView is configured', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hello world']]);

    $this->get(route('inlay.admin.pages.preview', $page))
        ->assertOk()
        ->assertSee('Unstyled fallback')
        ->assertSee('Hello world');
});

it('renders through the host-configured previewView, receiving $page and $composition', function () {
    Inlay::previewView('host-preview');

    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hello world']]);

    $this->get(route('inlay.admin.pages.preview', $page))
        ->assertOk()
        ->assertSee('host-frame-marker', false)
        ->assertSee($page->title)
        ->assertSee('Hello world');
});

it('previews the current draft state, not the last published revision', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Draft heading']]);

    $this->get(route('inlay.admin.pages.preview', $page))
        ->assertSee('Draft heading');
});

it('tags each section with its id and name so a click in the editor preview can select it', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hello world']]);

    $this->get(route('inlay.admin.pages.preview', $page))
        ->assertOk()
        ->assertSee('data-inlay-section="'.$section->id.'"', false)
        ->assertSee('data-inlay-section-label="Hero"', false)
        ->assertSee('inlay:select-section', false);
});
