<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;
use WeArePixel\Inlay\Tests\Fixtures\BrowserQuoteSection;

beforeEach(function () {
    config(['inlay.blocks' => ['hero' => BrowserHeroSection::class, 'quote' => BrowserQuoteSection::class]]);
});

it('shows the newly selected section\'s values, not the previous section\'s', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $first = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => ['heading' => 'First heading']]);
    $second = $page->sections()->create(['handle' => 'hero', 'order' => 1, 'settings' => ['heading' => 'Second heading']]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click("@tree-item-{$first->id}")
        ->assertValue('@setting-heading', 'First heading')
        ->click('@back-to-blocks')
        ->click("@tree-item-{$second->id}")
        ->assertValue('@setting-heading', 'Second heading');
});

it('shows each field\'s own value after switching to a section with different fields', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $hero = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => ['heading' => 'Hero heading', 'showCta' => true]]);
    $quote = $page->sections()->create(['handle' => 'quote', 'order' => 1, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click("@tree-item-{$hero->id}")
        ->assertValue('@setting-heading', 'Hero heading')
        ->click('@back-to-blocks')
        ->click("@tree-item-{$quote->id}")
        ->assertValue('@setting-quote', 'Quote text')
        ->assertValue('@setting-author', 'Author name');
});
