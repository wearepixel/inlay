<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

/**
 * A regression guard for the Beta 0.1 icon migration - every icon in the
 * admin used to be an HTML entity (&times;, &#10697;) or a literal glyph
 * (::) rather than a real, consistent icon set. This locks in that they
 * stay gone from the views most likely to reintroduce one.
 */
beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('renders the editor with real SVG icons, not HTML-entity glyphs', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    $html = Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->html();

    expect($html)
        ->not->toContain('&times;')
        ->not->toContain('&#10697;')
        ->not->toContain('&#9662;')
        ->not->toContain('&#8635;')
        ->toContain('inlay-icon');
});

it('shares one icon component definition across the admin', function () {
    expect(file_exists(__DIR__.'/../../resources/views/components/icon.blade.php'))->toBeTrue();
});
