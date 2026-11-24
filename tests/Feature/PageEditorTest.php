<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('adds a section with no stored overrides, resolving effective settings from the section defaults', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'test-hero')
        ->assertOk();

    $section = $page->sections()->first();

    expect($section->handle)->toBe('test-hero')
        // Sparse by design: a freshly-added section stores nothing at
        // all, relying entirely on its own constructor defaults until an
        // editor changes a field.
        ->and($section->settings)->toBe([]);
});

it('persists drag-reordering', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $first = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $second = $page->sections()->create(['handle' => 'test-hero', 'order' => 1, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('updateOrder', [$second->id, $first->id])
        ->assertOk();

    expect($first->fresh()->order)->toBe(1)
        ->and($second->fresh()->order)->toBe(0);
});

it('loads a selected section into editingProps with effective settings, and persists only the field that was edited', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
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
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hi', 'layout' => 'image-right']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('publish')
        ->assertDispatched('page-published');

    expect($page->fresh()->status)->toBe(PageStatus::Published)
        ->and($page->revisions()->count())->toBe(1);
});

it('records a UUID user as the publisher once an existing install switches user_key_type to uuid', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    app(PublishPage::class)->execute($page);

    config(['inlay.user_key_type' => 'uuid']);
    (require __DIR__.'/../../database/migrations/2026_11_24_000001_change_user_columns_to_configured_key_type.php')->up();

    $userId = (string) Str::uuid();

    Livewire::actingAs(new GenericUser(['id' => $userId]))
        ->test(PageEditor::class, ['page' => $page])
        ->call('publish')
        ->assertDispatched('page-published');

    expect(Schema::getColumnType('page_revisions', 'created_by'))->toBe('varchar')
        ->and($page->revisions()->orderBy('id')->pluck('created_by')->all())->toBe([null, $userId]);
});

it('duplicates a section, copying its stored settings and selecting the copy', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Original']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('duplicateSection', $section->id)
        ->assertOk();

    expect($page->sections()->count())->toBe(2);

    $duplicate = $page->sections()->where('id', '!=', $section->id)->sole();

    expect($duplicate->handle)->toBe('test-hero')
        ->and($duplicate->settings)->toBe(['heading' => 'Original']);
});

it('deletes a section and clears the selection if it was selected', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSet('selectedSectionId', $section->id)
        ->call('deleteSection', $section->id)
        ->assertSet('selectedSectionId', null);

    expect($page->sections()->count())->toBe(0);
});

it('bumps the preview version on a mutating action so the preview iframe is forced to reload', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSet('previewVersion', 0)
        ->call('addSection', 'test-hero')
        ->assertSet('previewVersion', 1);
});
