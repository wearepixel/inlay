<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Actions\RestorePageRevision;
use WearePixel\BladePageBuilder\Models\Page;

it('overwrites live sections from a revision snapshot and creates a new revision', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Original']]);

    $original = (new PublishPage())->execute($page);

    $page->sections()->first()->update(['props' => ['heading' => 'Edited']]);
    (new PublishPage())->execute($page);

    expect($page->revisions()->count())->toBe(2);

    $restored = (new RestorePageRevision(new PublishPage()))->execute($original, createdBy: 3);

    expect($page->sections()->first()->props)->toBe(['heading' => 'Original'])
        ->and($page->revisions()->count())->toBe(3)
        ->and($restored->label)->toBe("Restored from revision #{$original->id}")
        ->and($restored->created_by)->toBe(3);
});
