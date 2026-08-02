<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]]);
});

it('stays closed until openPicker is called, and closes again on closePicker/addSection', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSet('showingPicker', false)
        ->call('openPicker')
        ->assertSet('showingPicker', true)
        ->call('closePicker')
        ->assertSet('showingPicker', false)
        ->call('openPicker')
        ->call('addSection', 'test-hero')
        ->assertSet('showingPicker', false);
});

it('groups registered blocks by their #[BlockMeta] category, defaulting to Uncategorised', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSeeHtml('Headers')
        ->assertSeeHtml('Uncategorised')
        ->assertSeeHtml('Hero')
        ->assertSee('A large banner with a heading and image.');
});

it("surfaces each block's picker metadata (label, description, category, keywords) as a data-search attribute for client-side filtering", function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSeeHtml('data-search="hero a large banner with a heading and image. headers"');
});
