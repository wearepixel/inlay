<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\PageDefinitionValidator;
use WeArePixel\Inlay\Tests\Fixtures\TestCollectionPageDefinition;
use WeArePixel\Inlay\Tests\Fixtures\TestCtaSection;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
        'test-cta' => TestCtaSection::class,
    ]]);
    // Required + locked + default: test-hero. Allowed: test-hero, test-faq.
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);
});

it('has no violations for a page that satisfies its definition', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    expect(app(PageDefinitionValidator::class)->validate($page))->toBe([]);
});

it('reports a missing required block', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $page->sections()->create(['handle' => 'test-faq', 'order' => 0, 'settings' => []]);

    $violations = app(PageDefinitionValidator::class)->validate($page);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->type)->toBe('missing_required_block')
        ->and($violations[0]->message)->toContain('is required by');
});

it('reports a disallowed block', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $page->sections()->create(['handle' => 'test-cta', 'order' => 1, 'settings' => []]);

    $violations = app(PageDefinitionValidator::class)->validate($page);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->type)->toBe('disallowed_block')
        ->and($violations[0]->blockHandle)->toBe('test-cta')
        ->and($violations[0]->message)->toContain('is not allowed on');
});

it('reports an unresolved definition', function () {
    $page = Page::create(['title' => 'Orphaned', 'definition' => 'no-longer-registered']);

    $violations = app(PageDefinitionValidator::class)->validate($page);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->type)->toBe('unresolved_definition');
});

it('reports an unresolved block referenced by a page section', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $page->sections()->create(['handle' => 'no-longer-registered-block', 'order' => 1, 'settings' => []]);

    $violations = app(PageDefinitionValidator::class)->validate($page);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->type)->toBe('unresolved_block')
        ->and($violations[0]->blockHandle)->toBe('no-longer-registered-block');
});
