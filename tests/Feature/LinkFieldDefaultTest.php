<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Tests\Fixtures\TestCtaWithDefaultSection;

/**
 * Regression test for a real bug: a Link field with a non-nullable,
 * real-object default (`LinkValue $cta = new LinkValue(...)`) crashed the
 * editor the moment a block using it was added or selected. Livewire has
 * to serialise editingProps between requests, and SectionRenderer's raw
 * (editor-facing) settings resolution knew how to flatten a BackedEnum or
 * ImageValue default back to a plain scalar, but not a LinkValue one - so
 * the raw LinkValue object landed in editingProps unchanged, and Livewire
 * has no synthesizer for it: "Property type not supported in Livewire".
 * ?LinkValue $cta = null (the only pattern the existing TestCtaSection
 * fixture covered) never exercised this path, since null passes through
 * untouched regardless.
 */
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
