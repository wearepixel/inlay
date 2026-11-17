<?php

declare(strict_types=1);

use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Actions\RestorePageRevision;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\PageDefinitionViolationException;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;
use WeArePixel\Inlay\Tests\Fixtures\TestLockedPositionV1;
use WeArePixel\Inlay\Tests\Fixtures\TestLockedPositionV2;
use WeArePixel\Inlay\Tests\Fixtures\TestShiftingPageDefinitionV1;
use WeArePixel\Inlay\Tests\Fixtures\TestShiftingPageDefinitionV2;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('overwrites live sections from a revision snapshot and creates a new revision', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Original']]);

    $original = app(PublishPage::class)->execute($page);

    $page->sections()->first()->update(['settings' => ['heading' => 'Edited']]);
    app(PublishPage::class)->execute($page);

    expect($page->revisions()->count())->toBe(2);

    $restored = app(RestorePageRevision::class)->execute($original, createdBy: 3);

    expect($page->sections()->first()->settings)->toBe(['heading' => 'Original', 'layout' => 'image-right', 'photo' => null])
        ->and($page->revisions()->count())->toBe(3)
        ->and($restored->label)->toBe("Restored from revision #{$original->id}")
        ->and($restored->created_by)->toBe(3);
});

it('restores into the draft only, without publishing, when the content no longer satisfies the current Page Definition', function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]]);
    app(PageDefinitionRegistry::class)->register(TestShiftingPageDefinitionV1::class);

    $page = Page::create(['title' => 'Home', 'definition' => 'shifting-page']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $original = app(PublishPage::class)->execute($page);

    // Simulates a developer having since changed what "shifting-page" (the
    // same handle the Page still points to) requires - the Page's own
    // definition column never changed.
    app()->instance(PageDefinitionRegistry::class, tap(
        new PageDefinitionRegistry(app(SectionRegistry::class)),
        fn (PageDefinitionRegistry $registry) => $registry->register(TestShiftingPageDefinitionV2::class),
    ));

    $result = app(RestorePageRevision::class)->execute($original);

    expect($result)->toBeNull()
        // Status is untouched, not reverted - the last successful publish
        // ($original) is still what's live; restore just didn't create a
        // *new* one on top of it.
        ->and($page->fresh()->status)->toBe(PageStatus::Published)
        ->and($page->revisions()->count())->toBe(1)
        ->and($page->sections()->first()->handle)->toBe('test-hero');

    // Publishing stays blocked until the draft is corrected...
    expect(fn () => app(PublishPage::class)->execute($page))
        ->toThrow(PageDefinitionViolationException::class);

    // ...and succeeds once it satisfies the (now current) definition.
    $page->sections()->create(['handle' => 'test-faq', 'order' => 1, 'settings' => []]);
    $republished = app(PublishPage::class)->execute($page);

    expect($page->revisions()->count())->toBe(2)
        ->and($republished->snapshot['sections'])->toHaveCount(2);
});

it('restores into the draft only when the restored content would move a section that is locked under the current definition', function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]]);
    app(PageDefinitionRegistry::class)->register(TestLockedPositionV1::class);

    $page = Page::create(['title' => 'Home', 'definition' => 'locked-position-page']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $page->sections()->create(['handle' => 'test-faq', 'order' => 1, 'settings' => []]);
    $original = app(PublishPage::class)->execute($page); // hero@0, faq@1 - unlocked at the time

    // A later, ordinary edit (still under v1, still unlocked) moves the
    // hero - legitimate when it happened.
    $page->sections()->where('handle', 'test-hero')->update(['order' => 1]);
    $page->sections()->where('handle', 'test-faq')->update(['order' => 0]);

    // Simulates a developer having since locked the hero under the same
    // "locked-position-page" handle - both allow/require the same sections,
    // so only the locked-position check can produce a violation here.
    app()->instance(PageDefinitionRegistry::class, tap(
        new PageDefinitionRegistry(app(SectionRegistry::class)),
        fn (PageDefinitionRegistry $registry) => $registry->register(TestLockedPositionV2::class),
    ));

    // The current draft has hero@1; $original would restore it to hero@0 -
    // a position change for a now-locked section.
    $result = app(RestorePageRevision::class)->execute($original);

    expect($result)->toBeNull()
        ->and($page->revisions()->count())->toBe(1);
});
