<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('drops keys not declared in the schema even if the client injects them via editingProps', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Old', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.injected_key', 'attacker value')
        ->assertOk();

    expect($section->fresh()->props)->not->toHaveKey('injected_key');
});
