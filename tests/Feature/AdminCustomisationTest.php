<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => ['test-hero' => TestHeroSection::class]]);
});

it('edits a field type with the view the host configured', function () {
    config(['inlay.admin.fields' => ['text' => 'admin.fields.brand-text']]);

    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hello']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSeeHtml('data-test="brand-text-heading"')
        ->set('editingProps.heading', 'Changed through the host view');

    expect($section->fresh()->settings['heading'])->toBe('Changed through the host view');
});

it('renders the host editor actions with the page', function () {
    config(['inlay.admin.editor_actions' => 'admin.editor-actions']);

    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSeeHtml('<a href="/home" data-test="view-live">');
});
