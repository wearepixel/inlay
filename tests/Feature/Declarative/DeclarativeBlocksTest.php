<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Declarative\DeclarativeBlocks;
use WeArePixel\Inlay\Models\DeclarativeBlock;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;

beforeEach(function () {
    config([
        'inlay.agent_blocks.enabled' => true,
        'inlay.styling.stylesheets' => [__DIR__.'/../../Fixtures/css/app.css'],
        'inlay.styling.vite_manifest' => '/nope',
    ]);
});

function simpleBlock(string $heading = 'Hello'): array
{
    return [
        'handle' => 'figma-band',
        'name' => 'Figma band',
        'settings' => [['name' => 'heading', 'type' => 'text', 'default' => $heading]],
        'template' => ['tag' => 'section', 'class' => 'py-20', 'children' => [['tag' => 'h2', 'text' => ['setting' => 'heading']]]],
    ];
}

it('makes a saved block available to the registry and renderer immediately, and updates it in place', function () {
    app(DeclarativeBlocks::class)->save(simpleBlock(), userId: 7);

    expect(app(BlockRegistry::class)->hasHandle('figma-band'))->toBeTrue()
        ->and(app(SectionRenderer::class)->renderSection(['handle' => 'figma-band']))->toBe('<section class="py-20"><h2>Hello</h2></section>');

    app(DeclarativeBlocks::class)->save(simpleBlock('Updated'), userId: 8);

    expect(app(SectionRenderer::class)->renderSection(['handle' => 'figma-band']))->toContain('Updated');
});

it('ignores stored blocks entirely while agent blocks are disabled', function () {
    app(DeclarativeBlocks::class)->save(simpleBlock(), userId: null);
    config(['inlay.agent_blocks.enabled' => false]);
    app(BlockRegistry::class)->forgetDeclarative();

    expect(app(BlockRegistry::class)->hasHandle('figma-band'))->toBeFalse();
});

it('refuses to delete a block that a page still uses', function () {
    app(DeclarativeBlocks::class)->save(simpleBlock(), userId: null);
    Page::create(['title' => 'Landing'])->sections()->create(['handle' => 'figma-band', 'order' => 0, 'settings' => []]);

    expect(fn () => app(DeclarativeBlocks::class)->delete('figma-band'))->toThrow(InvalidCompositionException::class, 'used by 1 section');

    expect(app(BlockRegistry::class)->hasHandle('figma-band'))->toBeTrue();
});

it('refuses to delete a block a published page still renders', function () {
    app(DeclarativeBlocks::class)->save(simpleBlock(), userId: null);
    $page = Page::create(['title' => 'Live']);
    $page->sections()->create(['handle' => 'figma-band', 'order' => 0, 'settings' => []]);
    app(PublishPage::class)->execute($page);
    $page->sections()->delete();

    expect(fn () => app(DeclarativeBlocks::class)->delete('figma-band'))->toThrow(InvalidCompositionException::class, 'published page');
});

it('sees a block saved by another process without a restart', function () {
    $registry = app(BlockRegistry::class);
    expect($registry->hasHandle('figma-band'))->toBeFalse();

    DeclarativeBlock::create(['handle' => 'figma-band', 'definition' => simpleBlock()]);
    Cache::forget('inlay:declarative-blocks');
    Cache::forever(DeclarativeBlocks::VERSION_KEY, DeclarativeBlocks::version() + 1);
    $this->travel(2)->seconds();

    expect($registry->hasHandle('figma-band'))->toBeTrue();
});
