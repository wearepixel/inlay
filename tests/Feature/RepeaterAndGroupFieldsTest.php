<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestGroupSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-faq' => TestFaqSection::class,
        'test-group' => TestGroupSection::class,
    ]]);
});

it('adds and removes repeater items, persisting each edit', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-faq', 'order' => 0, 'settings' => ['items' => []]]);

    $test = Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->call('addRepeaterItem', 'items')
        ->assertOk();

    expect($section->fresh()->settings['items'])->toHaveCount(1);

    $test->set('editingProps.items.0.question', 'What is this?')->assertOk();

    expect($section->fresh()->settings['items'][0]['question'])->toBe('What is this?');

    $test->call('removeRepeaterItem', 'items', 0)->assertOk();

    expect($section->fresh()->settings['items'])->toBe([]);
});

it('defaults a Group field to an array, not an empty string, when a section is added', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'test-group')
        ->assertOk();

    $section = $page->sections()->first();

    expect($section->handle)->toBe('test-group')
        // A freshly-added section stores no overrides at all - `author`
        // resolves entirely from TestGroupSection's own constructor
        // default, per the sparse effective-settings model.
        ->and($section->settings)->toBe([]);
});
