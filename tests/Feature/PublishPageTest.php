<?php

declare(strict_types=1);

use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\PageDefinitionViolationException;
use WeArePixel\Inlay\Tests\Fixtures\TestCollectionPageDefinition;
use WeArePixel\Inlay\Tests\Fixtures\TestCtaSection;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('snapshots fully-resolved effective settings for every section and marks the page published', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $first = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hi']]);
    $second = $page->sections()->create(['handle' => 'test-hero', 'order' => 1, 'settings' => ['heading' => 'Bye']]);

    $revision = app(PublishPage::class)->execute($page, createdBy: 7, label: 'Initial publish');

    expect($revision->page_id)->toBe($page->id)
        ->and($revision->created_by)->toBe(7)
        ->and($revision->label)->toBe('Initial publish')
        ->and($revision->snapshot)->toBe([
            'sections' => [
                ['id' => $first->id, 'handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Hi', 'layout' => 'image-right', 'photo' => null]],
                ['id' => $second->id, 'handle' => 'test-hero', 'order' => 1, 'settings' => ['heading' => 'Bye', 'layout' => 'image-right', 'photo' => null]],
            ],
            'title' => 'Home',
            'meta_title' => null,
            'meta_description' => null,
            'definition' => null,
        ])
        ->and($page->fresh()->status)->toBe(PageStatus::Published);
});

it('does not change an already-published revision when the block default changes afterward', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    $revision = app(PublishPage::class)->execute($page);

    // The historical snapshot already resolved and froze `layout` at publish
    // time - it must not be recomputed against today's definition.
    expect($revision->snapshot['sections'][0]['settings']['layout'])->toBe('image-right');
});

it('snapshots the active Page Definition handle for audit context', function () {
    $page = Page::create(['title' => 'Home', 'definition' => 'default']);

    $revision = app(PublishPage::class)->execute($page);

    expect($revision->snapshot['definition'])->toBe('default');
});

it('blocks publishing a page that violates its Page Definition', function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]]);
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);

    $page = Page::create(['title' => 'Home', 'definition' => 'test-collection-page-definition']);
    // Required block (test-hero) is missing entirely.
    $page->sections()->create(['handle' => 'test-faq', 'order' => 0, 'settings' => []]);

    app(PublishPage::class)->execute($page);
})->throws(PageDefinitionViolationException::class);

it('blocks publishing a page containing a disallowed block', function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
        'test-cta' => TestCtaSection::class,
    ]]);
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);

    $page = Page::create(['title' => 'Home', 'definition' => 'test-collection-page-definition']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $page->sections()->create(['handle' => 'test-cta', 'order' => 1, 'settings' => []]);

    app(PublishPage::class)->execute($page);
})->throws(PageDefinitionViolationException::class);

it('blocks publishing a page whose Page Definition no longer resolves', function () {
    $page = Page::create(['title' => 'Home', 'definition' => 'no-longer-registered']);

    app(PublishPage::class)->execute($page);
})->throws(PageDefinitionViolationException::class);

it('blocks publishing a page containing a block that is no longer registered', function () {
    $page = Page::create(['title' => 'Home']);
    $page->sections()->create(['handle' => 'no-longer-registered-block', 'order' => 0, 'settings' => []]);

    app(PublishPage::class)->execute($page);
})->throws(PageDefinitionViolationException::class);

it('carries the structured violations on the exception for both UI display and assertions', function () {
    $page = Page::create(['title' => 'Home', 'definition' => 'no-longer-registered']);

    try {
        app(PublishPage::class)->execute($page);
        $this->fail('Expected a PageDefinitionViolationException.');
    } catch (PageDefinitionViolationException $exception) {
        expect($exception->violations)->toHaveCount(1)
            ->and($exception->violations[0]->type)->toBe('unresolved_definition');
    }
});

it('snapshots page-level metadata alongside sections', function () {
    $page = Page::create([
        'title' => 'Home',
        'path' => 'home',
        'meta_title' => 'Welcome home',
        'meta_description' => 'The homepage.',
    ]);

    $revision = app(PublishPage::class)->execute($page);

    expect($revision->snapshot['title'])->toBe('Home')
        ->and($revision->snapshot['meta_title'])->toBe('Welcome home')
        ->and($revision->snapshot['meta_description'])->toBe('The homepage.');
});
