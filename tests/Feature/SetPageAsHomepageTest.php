<?php

declare(strict_types=1);

use WeArePixel\Inlay\Actions\SetPageAsHomepage;
use WeArePixel\Inlay\Models\Page;

it('sets a page as the homepage', function () {
    $page = Page::create(['title' => 'Home']);

    app(SetPageAsHomepage::class)->execute($page);

    expect($page->fresh()->is_homepage)->toBeTrue();
});

it('unsets the previous homepage when a new one is set', function () {
    $previous = Page::create(['title' => 'Old Home', 'is_homepage' => true]);
    $next = Page::create(['title' => 'New Home']);

    app(SetPageAsHomepage::class)->execute($next);

    expect($previous->fresh()->is_homepage)->toBeFalse()
        ->and($next->fresh()->is_homepage)->toBeTrue();
});

it('leaves the flag with no orphaned reference when the homepage page is deleted', function () {
    $page = Page::create(['title' => 'Home', 'is_homepage' => true]);

    $page->delete();

    expect(Page::query()->homepage()->exists())->toBeFalse();
});
