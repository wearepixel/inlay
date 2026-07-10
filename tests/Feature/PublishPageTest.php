<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Enums\PageStatus;
use WearePixel\BladePageBuilder\Models\Page;

it('snapshots the current sections into a revision and marks the page published', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Hi']]);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 1, 'props' => ['heading' => 'Bye']]);

    $revision = (new PublishPage)->execute($page, createdBy: 7, label: 'Initial publish');

    expect($revision->page_id)->toBe($page->id)
        ->and($revision->created_by)->toBe(7)
        ->and($revision->label)->toBe('Initial publish')
        ->and($revision->snapshot)->toBe([
            ['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Hi']],
            ['section_type' => 'test-hero', 'order' => 1, 'props' => ['heading' => 'Bye']],
        ])
        ->and($page->fresh()->status)->toBe(PageStatus::Published);
});
