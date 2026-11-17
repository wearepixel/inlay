<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Declarative\DeclarativeSections;
use WeArePixel\Inlay\Models\DeclarativeSection;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;

beforeEach(function () {
    config([
        'inlay.agent_sections.enabled' => true,
        'inlay.styling.stylesheets' => [__DIR__.'/../../Fixtures/css/app.css'],
        'inlay.styling.vite_manifest' => '/nope',
    ]);
});

function simpleSection(string $heading = 'Hello'): array
{
    return [
        'handle' => 'figma-band',
        'name' => 'Figma band',
        'settings' => [['name' => 'heading', 'type' => 'text', 'default' => $heading]],
        'template' => ['tag' => 'section', 'class' => 'py-20', 'children' => [['tag' => 'h2', 'text' => ['setting' => 'heading']]]],
    ];
}

it('makes a saved section available to the registry and renderer immediately, and updates it in place', function () {
    app(DeclarativeSections::class)->save(simpleSection(), userId: 7);

    expect(app(SectionRegistry::class)->hasHandle('figma-band'))->toBeTrue()
        ->and(app(SectionRenderer::class)->renderSection(['handle' => 'figma-band']))->toBe('<section class="py-20"><h2>Hello</h2></section>');

    app(DeclarativeSections::class)->save(simpleSection('Updated'), userId: 8);

    expect(app(SectionRenderer::class)->renderSection(['handle' => 'figma-band']))->toContain('Updated');
});

it('ignores stored sections entirely while agent sections are disabled', function () {
    app(DeclarativeSections::class)->save(simpleSection(), userId: null);
    config(['inlay.agent_sections.enabled' => false]);
    app(SectionRegistry::class)->forgetDeclarative();

    expect(app(SectionRegistry::class)->hasHandle('figma-band'))->toBeFalse();
});

it('refuses to delete a section that a page still uses', function () {
    app(DeclarativeSections::class)->save(simpleSection(), userId: null);
    Page::create(['title' => 'Landing'])->sections()->create(['handle' => 'figma-band', 'order' => 0, 'settings' => []]);

    expect(fn () => app(DeclarativeSections::class)->delete('figma-band'))->toThrow(InvalidCompositionException::class, 'used by 1 section');

    expect(app(SectionRegistry::class)->hasHandle('figma-band'))->toBeTrue();
});

it('refuses to delete a section a published page still renders', function () {
    app(DeclarativeSections::class)->save(simpleSection(), userId: null);
    $page = Page::create(['title' => 'Live']);
    $page->sections()->create(['handle' => 'figma-band', 'order' => 0, 'settings' => []]);
    app(PublishPage::class)->execute($page);
    $page->sections()->delete();

    expect(fn () => app(DeclarativeSections::class)->delete('figma-band'))->toThrow(InvalidCompositionException::class, 'published page');
});

it('sees a section saved by another process without a restart', function () {
    $registry = app(SectionRegistry::class);
    expect($registry->hasHandle('figma-band'))->toBeFalse();

    DeclarativeSection::create(['handle' => 'figma-band', 'definition' => simpleSection()]);
    Cache::forget('inlay:declarative-sections');
    Cache::forever(DeclarativeSections::VERSION_KEY, DeclarativeSections::version() + 1);
    $this->travel(2)->seconds();

    expect($registry->hasHandle('figma-band'))->toBeTrue();
});
