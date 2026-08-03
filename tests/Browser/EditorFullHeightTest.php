<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => ['hero' => BrowserHeroSection::class]]);
});

/**
 * Regression test for a real bug: .inlay-editor's "flex: 1" had no real
 * flex context to grow into. A Livewire component always renders behind a
 * single root element - for page-editor.blade.php, that element sat
 * between .inlay-main's flex column and .inlay-editor with no flex/height
 * styling of its own (see .inlay-editor-page in inlay.css), so it
 * defaulted to a plain block box sized to its content. .inlay-editor's
 * own "flex: 1" then had nothing real to grow into and silently fell
 * back to its content height instead.
 *
 * This only ever looked correct by coincidence, when a short-enough
 * viewport happened to roughly match that content height - a normal
 * ~900px window hid it entirely. A very tall viewport (a large monitor,
 * not an edge case) makes the gap obvious: the editor stops partway down
 * the page and leaves a dead white area below it.
 */
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
