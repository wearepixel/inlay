<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Enums\PageStatus;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('adds a section with default props derived from its schema', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'test-hero')
        ->assertOk();

    $section = $page->sections()->first();

    expect($section->section_type)->toBe('test-hero')
        ->and($section->props)->toBe(['heading' => '', 'layout' => '']);
});

it('persists drag-reordering', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $first = $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => []]);
    $second = $page->sections()->create(['section_type' => 'test-hero', 'order' => 1, 'props' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('updateOrder', [$second->id, $first->id])
        ->assertOk();

    expect($first->fresh()->order)->toBe(1)
        ->and($second->fresh()->order)->toBe(0);
});

it('loads a selected section into editingProps and persists edits', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Old', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSet('editingProps', ['heading' => 'Old', 'layout' => 'image-right'])
        ->set('editingProps.heading', 'New heading')
        ->assertOk();

    expect($section->fresh()->props['heading'])->toBe('New heading');
});

it('publishes the page', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Hi', 'layout' => 'image-right']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('publish')
        ->assertDispatched('page-published');

    expect($page->fresh()->status)->toBe(PageStatus::Published)
        ->and($page->revisions()->count())->toBe(1);
});
