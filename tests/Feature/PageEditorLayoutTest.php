<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => \WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection::class,
    ]]);
});

it('renders the section list with drag data attributes, the add-section picker, and the preview pane', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Hi', 'layout' => 'image-right']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSee('data-section-id="' . $section->id . '"', escape: false)
        ->assertSee('test-hero')
        ->assertSeeHtml('wire:click="addSection(\'test-hero\')"')
        ->assertSeeHtml('$wire.updateOrder')
        ->assertSee('Publish');
});
