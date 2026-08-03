<?php

declare(strict_types=1);

use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Models\Page;
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
