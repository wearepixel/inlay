<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestCtaSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
        'test-cta' => TestCtaSection::class,
    ]]);
});

it('drops keys not declared in the section definition even if the client injects them via editingProps', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-hero',
        'order' => 0,
        'settings' => ['heading' => 'Old', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.injected_key', 'attacker value')
        ->assertOk();

    expect($section->fresh()->settings)->not->toHaveKey('injected_key');
});

it('coerces a declared scalar field set to an array via editingProps rather than persisting the array', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-hero',
        'order' => 0,
        'settings' => ['heading' => 'Old', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.heading', ['malicious' => 'array value'])
        ->assertOk();

    $persisted = $section->fresh()->settings['heading'];

    expect($persisted)->toBeString();
    expect($persisted)->not->toBeArray();
});

it('coerces a Link field set to a plain scalar via editingProps back to a safe structured default', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-cta',
        'order' => 0,
        'settings' => ['cta' => ['label' => 'Old', 'url' => '/old', 'openInNewTab' => false]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.cta', 'attacker value')
        ->assertOk();

    $persisted = $section->fresh()->settings['cta'];

    expect($persisted)->toBe(['label' => '', 'url' => '', 'openInNewTab' => false]);
});

it('preserves a legitimate Link field value through editingProps', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-cta',
        'order' => 0,
        'settings' => ['cta' => ['label' => '', 'url' => '', 'openInNewTab' => false]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.cta.label', 'Get started')
        ->set('editingProps.cta.url', 'https://example.com')
        ->set('editingProps.cta.openInNewTab', true)
        ->assertOk();

    expect($section->fresh()->settings['cta'])->toBe([
        'label' => 'Get started',
        'url' => 'https://example.com',
        'openInNewTab' => true,
    ]);
});

it('strips a javascript: URL from a Link field rather than persisting it', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-cta',
        'order' => 0,
        'settings' => ['cta' => ['label' => '', 'url' => '', 'openInNewTab' => false]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.cta.label', 'Click me')
        ->set('editingProps.cta.url', 'javascript:alert(1)')
        ->assertOk();

    expect($section->fresh()->settings['cta'])->toBe([
        'label' => 'Click me',
        'url' => '',
        'openInNewTab' => false,
    ]);
});

it('allows a relative URL through a Link field unchanged', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-cta',
        'order' => 0,
        'settings' => ['cta' => ['label' => '', 'url' => '', 'openInNewTab' => false]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.cta.url', '/about')
        ->assertOk();

    expect($section->fresh()->settings['cta']['url'])->toBe('/about');
});

it('coerces an invalid enum value set via editingProps back to the constructor default rather than persisting it', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-hero',
        'order' => 0,
        'settings' => ['heading' => 'Old', 'layout' => 'image-left'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.layout', 'not-a-real-case')
        ->assertOk();

    expect($section->fresh()->settings['layout'])->toBe('image-right');
});
