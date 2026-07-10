<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestFaqSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-faq' => TestFaqSection::class,
    ]]);
});

it('adds and removes repeater items, persisting each edit', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['section_type' => 'test-faq', 'order' => 0, 'props' => ['items' => []]]);

    $test = Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->call('addRepeaterItem', 'items')
        ->assertOk();

    expect($section->fresh()->props['items'])->toHaveCount(1);

    $test->set('editingProps.items.0.question', 'What is this?')->assertOk();

    expect($section->fresh()->props['items'][0]['question'])->toBe('What is this?');

    $test->call('removeRepeaterItem', 'items', 0)->assertOk();

    expect($section->fresh()->props['items'])->toBe([]);
});
