<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('renders the latest published revision, ignoring unpublished draft edits', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Published heading']]);
    (new PublishPage)->execute($page);

    $page->sections()->first()->update(['props' => ['heading' => 'Unpublished draft edit']]);

    $html = Blade::render('<x-page-builder::render :page="$page" />', ['page' => $page]);

    expect($html)
        ->toContain('Published heading')
        ->not->toContain('Unpublished draft edit');
});

it('renders nothing for a page that has never been published', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $html = Blade::render('<x-page-builder::render :page="$page" />', ['page' => $page]);

    expect(trim($html))->toBe('');
});
