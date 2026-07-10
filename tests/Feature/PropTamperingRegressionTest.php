<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestCtaSection;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => TestHeroSection::class,
        'test-cta' => TestCtaSection::class,
    ]]);
});

it('drops keys not declared in the schema even if the client injects them via editingProps', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Old', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.injected_key', 'attacker value')
        ->assertOk();

    expect($section->fresh()->props)->not->toHaveKey('injected_key');
});

it('coerces a declared scalar field set to an array via editingProps rather than persisting the array', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Old', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.heading', ['malicious' => 'array value'])
        ->assertOk();

    $persisted = $section->fresh()->props['heading'];

    expect($persisted)->toBeString();
    expect($persisted)->not->toBeArray();
});

it('coerces a Link field set to a plain scalar via editingProps back to a safe structured default', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-cta',
        'order' => 0,
        'props' => ['cta' => ['label' => 'Old', 'url' => '/old', 'new_tab' => false]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.cta', 'attacker value')
        ->assertOk();

    $persisted = $section->fresh()->props['cta'];

    expect($persisted)->toBe(['label' => '', 'url' => '', 'new_tab' => false]);
});

it('preserves a legitimate Link field value through editingProps', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-cta',
        'order' => 0,
        'props' => ['cta' => ['label' => '', 'url' => '', 'new_tab' => false]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.cta.label', 'Get started')
        ->set('editingProps.cta.url', 'https://example.com')
        ->set('editingProps.cta.new_tab', true)
        ->assertOk();

    expect($section->fresh()->props['cta'])->toBe([
        'label' => 'Get started',
        'url' => 'https://example.com',
        'new_tab' => true,
    ]);
});

it('strips a javascript: URL from a Link field rather than persisting it', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-cta',
        'order' => 0,
        'props' => ['cta' => ['label' => '', 'url' => '', 'new_tab' => false]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.cta.label', 'Click me')
        ->set('editingProps.cta.url', 'javascript:alert(1)')
        ->assertOk();

    expect($section->fresh()->props['cta'])->toBe([
        'label' => 'Click me',
        'url' => '',
        'new_tab' => false,
    ]);
});

it('allows a relative URL through a Link field unchanged', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-cta',
        'order' => 0,
        'props' => ['cta' => ['label' => '', 'url' => '', 'new_tab' => false]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.cta.url', '/about')
        ->assertOk();

    expect($section->fresh()->props['cta']['url'])->toBe('/about');
});
