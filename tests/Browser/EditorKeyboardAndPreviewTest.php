<?php

declare(strict_types=1);

use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

beforeEach(function () {
    config(['inlay.sections' => ['hero' => BrowserHeroSection::class]]);
});

it('reorders components with a keyboard shortcut, not just by dragging', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $first = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => ['heading' => 'First']]);
    $second = $page->sections()->create(['handle' => 'hero', 'order' => 1, 'settings' => ['heading' => 'Second']]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    // Deliberately not preceded by a click on the row: clicking a tree row
    // now opens its settings and replaces the tree pane entirely, which
    // would hide the very row this test sends keys to. keys() focuses its
    // target element itself (Playwright's locator.press()), so a real
    // click isn't needed just to focus it - matching the actual keyboard
    // interaction of tabbing to a row and reordering it without ever
    // opening its settings.
    $editor->keys("[data-section-id=\"{$first->id}\"]", 'Alt+ArrowDown')
        ->wait(1);

    expect($first->fresh()->order)->toBe(1)
        ->and($second->fresh()->order)->toBe(0);
});

it('deletes the focused component with the Delete key, after confirmation', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");
    $editor->script('window.confirm = () => true;');

    // See the reorder test above for why this isn't preceded by a click.
    $editor->keys("[data-section-id=\"{$section->id}\"]", 'Delete')
        ->wait(1);

    expect($page->sections()->count())->toBe(0);
});

it('navigates and adds a component from the picker with arrow keys and Enter', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click('@open-picker')
        ->keys('@picker-search', 'ArrowDown')
        ->assertVisible('.inlay-picker__card.is-highlighted');

    $editor->keys('@picker-search', 'Enter')->wait(1);

    expect($page->sections()->count())->toBe(1);
});

it('remembers the selected preview viewport and actually narrows the iframe', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $desktopWidth = $editor->script('document.querySelector(".inlay-preview__frame").getBoundingClientRect().width');

    $editor->click('Mobile')->wait(1); // the width change is CSS-transitioned

    expect($editor->script('document.querySelector(".inlay-preview__viewport").className'))
        ->toContain('inlay-preview__viewport--mobile');

    expect($editor->script('localStorage.getItem("inlay-preview-viewport")'))->toBe('mobile');

    // Not just the class name: the iframe must actually be narrower. A
    // manual review caught a real regression here - the base
    // .inlay-preview__frame rule's flex: 1 silently overrode the
    // viewport-specific width rules regardless of which class was applied,
    // since flex-basis wins over width for a flex item's main-axis size.
    $mobileWidth = $editor->script('document.querySelector(".inlay-preview__frame").getBoundingClientRect().width');

    expect($mobileWidth)->toBeLessThan($desktopWidth)
        ->and($mobileWidth)->toBeLessThanOrEqual(390);
});
