<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Tests\Fixtures\TestCtaWithDefaultSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-cta-with-default' => TestCtaWithDefaultSection::class,
    ]]);
});

it('resolves a non-nullable Link default to a plain array for the editor, not a raw LinkValue object', function () {
    $definition = app(BlockRegistry::class)->definition('test-cta-with-default');

    $raw = app(SectionRenderer::class)->resolveEffectiveRawSettings($definition, []);

    expect($raw['cta'])->toBe(['label' => 'Get started', 'url' => '/menu', 'openInNewTab' => false]);
});

it('adds and selects a block with a defaulted Link field without a Livewire serialization error', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'test-cta-with-default')
        ->assertOk()
        ->assertSet('editingProps.cta', ['label' => 'Get started', 'url' => '/menu', 'openInNewTab' => false]);

    $section = $page->sections()->sole();

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertOk();
});
