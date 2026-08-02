<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\Inlay\Enums\PageStatus;
use WearePixel\Inlay\Livewire\PageEditor;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('adds a section with no stored overrides, resolving effective settings from the component defaults', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'test-hero')
        ->assertOk();

    $section = $page->sections()->first();

    expect($section->handle)->toBe('test-hero')
        // Sparse by design: a freshly-added component stores nothing at
        // all, relying entirely on its own constructor defaults until an
        // editor changes a field.
        ->and($section->settings)->toBe([]);
});

it('persists drag-reordering', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $first = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $second = $page->sections()->create(['handle' => 'test-hero', 'order' => 1, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('updateOrder', [$second->id, $first->id])
        ->assertOk();

    expect($first->fresh()->order)->toBe(1)
        ->and($second->fresh()->order)->toBe(0);
});

it('loads a selected section into editingProps with effective settings, and persists only the field that was edited', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-hero',
        'order' => 0,
        'settings' => ['heading' => 'Old', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSet('editingProps', ['heading' => 'Old', 'layout' => 'image-right', 'photo' => null])
        ->set('editingProps.heading', 'New heading')
        ->assertOk();

    expect($section->fresh()->settings)->toBe([
        'heading' => 'New heading',
        'layout' => 'image-right',
    ]);
});

it('publishes the page', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hi', 'layout' => 'image-right']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('publish')
        ->assertDispatched('page-published');

    expect($page->fresh()->status)->toBe(PageStatus::Published)
        ->and($page->revisions()->count())->toBe(1);
});
