<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => ['hero' => BrowserHeroSection::class]]);
});

// Regression: .inlay-editor-page in inlay.css restores the flex chain through page-editor's Livewire root element.
it('fills a very tall viewport, not just a normal one', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit")->resize(1440, 2200);

    $mainHeight = (int) $editor->script('document.querySelector(".inlay-main").getBoundingClientRect().height');
    $editorHeight = (int) $editor->script('document.querySelector(".inlay-editor").getBoundingClientRect().height');
    $topbarHeight = (int) $editor->script('document.querySelector(".inlay-topbar").getBoundingClientRect().height');

    // The editor should account for essentially all of .inlay-main's
    // height once the topbar is subtracted - a few pixels of slack for
    // sub-pixel layout rounding, not the ~1800px gap the bug left behind.
    expect($editorHeight)->toBeGreaterThan($mainHeight - $topbarHeight - 5);

    $editor->assertNoJavaScriptErrors();
});
