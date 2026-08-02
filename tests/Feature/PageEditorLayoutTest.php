<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\Inlay\Livewire\PageEditor;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('renders the section list with drag data attributes, the add-section picker, and the preview pane', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hi', 'layout' => 'image-right']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSee('data-section-id="'.$section->id.'"', escape: false)
        ->assertSee('test-hero')
        ->assertSeeHtml('wire:click="addSection(\'test-hero\')"')
        ->assertSeeHtml('$wire.updateOrder')
        ->assertSee('Publish');
});
