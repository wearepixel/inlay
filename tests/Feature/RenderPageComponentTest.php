<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use WearePixel\Inlay\Actions\PublishPage;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('renders the latest published revision, ignoring unpublished draft edits', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Published heading']]);
    app(PublishPage::class)->execute($page);

    $page->sections()->first()->update(['settings' => ['heading' => 'Unpublished draft edit']]);

    $html = Blade::render('<x-inlay::render :page="$page" />', ['page' => $page]);

    expect($html)
        ->toContain('Published heading')
        ->not->toContain('Unpublished draft edit');
});

it('renders nothing for a page that has never been published', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $html = Blade::render('<x-inlay::render :page="$page" />', ['page' => $page]);

    expect(trim($html))->toBe('');
});
