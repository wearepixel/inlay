<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

/**
 * Beta 0.1 polish additions: the contextual panel's collapse toggle, the
 * "?" keyboard-shortcuts reference, and the block picker's search-match
 * highlighting - see resources/dist/inlay.js's inlayEditorSidebar,
 * inlayShortcutsHelp, and inlayBlockPicker.highlight().
 */
beforeEach(function () {
    config(['inlay.blocks' => ['hero' => BrowserHeroSection::class]]);
});

it('collapses and expands the contextual panel via the toggle, remembering the choice', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $widthBefore = (int) $editor->script(
        'document.querySelector(".inlay-editor__sidebar").getBoundingClientRect().width',
    );
    expect($widthBefore)->toBeGreaterThan(200);

    $editor->click('@collapse-panel')->wait(1);

    $widthAfter = (int) $editor->script(
        'document.querySelector(".inlay-editor__sidebar").getBoundingClientRect().width',
    );
    expect($widthAfter)->toBe(0);
    expect($editor->script('localStorage.getItem("inlay-sidebar-collapsed")'))->toBe('1');

    $editor->click('@collapse-panel')->wait(1);

    $widthRestored = (int) $editor->script(
        'document.querySelector(".inlay-editor__sidebar").getBoundingClientRect().width',
    );
    expect($widthRestored)->toBe($widthBefore);
    expect($editor->script('localStorage.getItem("inlay-sidebar-collapsed")'))->toBe('0');

    $editor->assertNoJavaScriptErrors();
});

it('opens the keyboard shortcuts overlay from the header and closes it with Escape', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->assertMissing('@shortcuts-overlay')
        ->click('@open-shortcuts')
        ->assertVisible('@shortcuts-overlay')
        ->assertSee('Keyboard shortcuts');

    $editor->keys('@shortcuts-overlay', 'Escape')
        ->assertMissing('@shortcuts-overlay')
        ->assertNoJavaScriptErrors();
});

it('highlights the matching substring of a block label while searching the picker', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click('@open-picker')
        ->fill('@picker-search', 'her')
        ->wait(1);

    $highlighted = $editor->script(
        'document.querySelector(\'[data-test="picker-block-hero"] mark\')?.textContent ?? null',
    );

    expect($highlighted)->toBe('Her');
    $editor->assertNoJavaScriptErrors();
});
