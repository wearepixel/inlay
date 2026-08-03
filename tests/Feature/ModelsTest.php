<?php

declare(strict_types=1);

use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Models\Page;

it('relates pages to sections and revisions, and casts settings/snapshot as arrays', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    $section = $page->sections()->create([
        'handle' => 'test-hero',
        'order' => 0,
        'settings' => ['heading' => 'Welcome'],
    ]);

    $revision = $page->revisions()->create([
        'snapshot' => [['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Welcome']]],
    ]);

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->isPublished())->toBeFalse()
        ->and($page->sections()->first()->id)->toBe($section->id)
        ->and($section->settings)->toBe(['heading' => 'Welcome'])
        ->and($page->revisions()->first()->id)->toBe($revision->id)
        ->and($revision->snapshot)->toBeArray()
        ->and($section->page->id)->toBe($page->id)
        ->and($revision->page->id)->toBe($page->id);
});
