<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\Inlay\Livewire\PageEditor;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\TestHeroSection;
use WearePixel\Inlay\Tests\Fixtures\TestTabbedSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-tabbed' => TestTabbedSection::class,
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('renders tab navigation only when a component declares more than one tab', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $tabbed = $page->sections()->create(['handle' => 'test-tabbed', 'order' => 0, 'settings' => []]);
    $plain = $page->sections()->create(['handle' => 'test-hero', 'order' => 1, 'settings' => []]);

    $test = Livewire::test(PageEditor::class, ['page' => $page]);

    $test->call('selectSection', $tabbed->id)
        ->assertSeeHtml('inlay-tabs')
        ->assertSee('Content')
        ->assertSee('Appearance')
        ->assertSee('SEO');

    $test->call('selectSection', $plain->id)
        ->assertDontSeeHtml('inlay-tabs');
});

it('renders section() fields under a named, collapsible group heading', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-tabbed', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSeeHtml('inlay-group-section')
        ->assertSee('Hero');
});

it('shows a required indicator and a field badge in the inspector', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-tabbed', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSeeHtml('inlay-required-indicator')
        ->assertSee('New'); // alignment's badge
});
