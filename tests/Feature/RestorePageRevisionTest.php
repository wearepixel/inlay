<?php

declare(strict_types=1);

use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Actions\RestorePageRevision;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('overwrites live sections from a revision snapshot and creates a new revision', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
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
