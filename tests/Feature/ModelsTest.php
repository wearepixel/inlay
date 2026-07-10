<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Enums\PageStatus;
use WearePixel\BladePageBuilder\Models\Page;

it('relates pages to sections and revisions, and casts props/snapshot as arrays', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Welcome'],
    ]);

    $revision = $page->revisions()->create([
        'snapshot' => [['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Welcome']]],
    ]);

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->isPublished())->toBeFalse()
        ->and($page->sections()->first()->id)->toBe($section->id)
        ->and($section->props)->toBe(['heading' => 'Welcome'])
        ->and($page->revisions()->first()->id)->toBe($revision->id)
        ->and($revision->snapshot)->toBeArray()
        ->and($section->page->id)->toBe($page->id)
        ->and($revision->page->id)->toBe($page->id);
});
