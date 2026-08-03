<?php

declare(strict_types=1);

use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Actions\RestorePageRevision;
use WeArePixel\Inlay\Models\Page;

it('falls back to the title when no meta title is set', function () {
    $page = Page::create(['title' => 'About Us']);

    expect($page->effectiveMetaTitle())->toBe('About Us');
});

it('uses the explicit meta title when one is set', function () {
    $page = Page::create(['title' => 'About Us', 'meta_title' => 'All About Us']);

    expect($page->effectiveMetaTitle())->toBe('All About Us');
});

it('has no effective meta description when none is set', function () {
    $page = Page::create(['title' => 'About Us']);

    expect($page->effectiveMetaDescription())->toBeNull();
});

it('does not change what a published page shows publicly when metadata is edited without republishing', function () {
    $page = Page::create(['title' => 'Original title', 'meta_description' => 'Original description']);
    app(PublishPage::class)->execute($page);

    $page->update(['title' => 'Edited title', 'meta_description' => 'Edited description']);

    $revision = $page->revisions()->first();

    expect($revision->snapshot['title'])->toBe('Original title')
        ->and($revision->snapshot['meta_description'])->toBe('Original description');
});

it('restores metadata alongside sections when restoring a revision', function () {
    $page = Page::create(['title' => 'Original title', 'meta_title' => 'Original meta']);
    $original = app(PublishPage::class)->execute($page);

    $page->update(['title' => 'Edited title', 'meta_title' => 'Edited meta']);
    app(PublishPage::class)->execute($page);

    app(RestorePageRevision::class)->execute($original);

    expect($page->fresh()->title)->toBe('Original title')
        ->and($page->fresh()->meta_title)->toBe('Original meta');
});
