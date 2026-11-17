<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('renders the section list with drag data attributes, the add-section picker, and the preview pane', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hi', 'layout' => 'image-right']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSee('data-section-id="'.$section->id.'"', escape: false)
        // The tree shows the section's registered display name and an
        // instance detail guessed from its "heading" setting, not the raw
        // handle - see SectionInstanceLabel.
        ->assertSee('Hero')
        ->assertSee('Hi')
        ->assertSeeHtml('x-data="inlayEditorSidebar')
        ->assertSee('Publish')
        ->call('openPicker')
        ->assertSeeHtml('wire:click="addSection(\'test-hero\')"');
});
