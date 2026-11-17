<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

/**
 * The two-pane editor redesign: a contextual sidebar (section tree OR a
 * selected section's settings, never both at once) beside a dominant
 * preview - replacing the old permanent three-column layout. See
 * InlayManager... no, see resources/dist/inlay.js's inlayEditorSidebar and
 * docs/inlay/v1/editor for the interaction model this exercises.
 */
beforeEach(function () {
    config(['inlay.sections' => ['hero' => BrowserHeroSection::class]]);
});

it('opens in section-tree mode by default, with the preview dominant and no permanent settings column', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->assertVisible('@tree-item-'.$page->sections()->sole()->id)
        ->assertMissing('@setting-heading')
        // The old three-column layout's own column class must be gone
        // entirely, not merely hidden - this is a real layout replacement.
        ->assertMissing('.inlay-editor__column');

    $sidebarWidth = $editor->script('document.querySelector(".inlay-editor__sidebar").getBoundingClientRect().width');
    $previewWidth = $editor->script('document.querySelector(".inlay-editor__preview-column").getBoundingClientRect().width');

    // The preview must be the dominant area - materially wider than the
    // old three-column layout ever gave it, not just wider than the
    // sidebar by some small margin.
    expect($previewWidth)->toBeGreaterThan($sidebarWidth * 1.5);
});

it('selecting a section replaces the tree with its settings, and back returns to the tree with focus restored', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click("@tree-item-{$section->id}")
        ->assertVisible('@setting-heading')
        ->assertMissing("@tree-item-{$section->id}")
        // Display name + a guessed instance detail from the "heading"
        // setting's default value, not the raw "hero" handle - see
        // SectionInstanceLabel.
        ->assertSee('Hero')
        ->assertSee('Welcome');

    $editor->click('@back-to-sections')
        ->assertVisible("@tree-item-{$section->id}")
        ->assertMissing('@setting-heading')
        // Still highlighted as selected, even though the sidebar no longer
        // shows its settings - selection and "which pane is showing" are
        // deliberately independent bits of state.
        ->assertAttribute("@tree-item-{$section->id}", 'aria-selected', 'true');

    expect($editor->script('document.activeElement.dataset.sectionId'))->toBe((string) $section->id);
});

it('opens settings with Enter and returns to the tree with Escape', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->keys("[data-section-id=\"{$section->id}\"]", 'Enter')
        ->assertVisible('@setting-heading');

    // Not 'body': Pest's locator guessing only recognises an explicit CSS
    // selector (leading #/./[, or a CSS special character) or a data-test
    // shorthand - a bare tag name like "body" falls through to a getByText()
    // search instead, which never resolves (there's no visible text "body"
    // on the page) and hangs. The already-visible heading field is a real,
    // reliably-present target - the escape.window listener catches the key
    // globally regardless of which element it's dispatched from.
    $editor->keys('@setting-heading', 'Escape')
        ->assertMissing('@setting-heading')
        ->assertVisible("@tree-item-{$section->id}");
});

it('adding from the picker selects the new section and opens its settings', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click('@open-picker')
        ->click('@picker-section-hero')
        ->assertVisible('@setting-heading')
        ->assertSee('Hero');

    $section = $page->sections()->sole();
    $editor->assertMissing("@tree-item-{$section->id}");
});

it('opens Page Settings from the header with no JavaScript error, and returns to the tree', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    // Regression check: the header lives outside the section tree/preview
    // pane's own markup - a real bug here had the "Page Settings" button's
    // x-on:click call an Alpine method defined on a *different* x-data
    // scope than the one wrapping the button, throwing "showPageSettings
    // is not defined" and leaving the tree showing instead of switching to
    // the Page Settings pane.
    $editor->click('Page Settings')
        ->assertVisible('#page-settings-title')
        ->assertNoJavaScriptErrors();

    $editor->click('@back-from-page-settings')
        ->assertMissing('#page-settings-title')
        ->assertSee('Sections');
});

it('safely returns to the tree if the selected section is deleted', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");
    $editor->script('window.confirm = () => true;');

    $editor->click("@tree-item-{$section->id}")
        ->assertVisible('@setting-heading')
        ->click('@back-to-sections');

    $editor->hover("@tree-item-{$section->id}")
        ->click("@delete-{$section->id}")
        ->wait(1);

    $editor->assertMissing('@setting-heading')
        ->assertSee('Add a section to start building this page.')
        ->assertNoJavaScriptErrors();

    expect($page->sections()->count())->toBe(0);
});

it('does not overflow horizontally at a narrow viewport, and switches panels via the segmented control', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit")->resize(390, 800);

    $scrollWidth = $editor->script('document.documentElement.scrollWidth');
    $clientWidth = $editor->script('document.documentElement.clientWidth');

    expect((int) $scrollWidth)->toBeLessThanOrEqual((int) $clientWidth);

    $editor->assertVisible('@tree-item-'.$page->sections()->sole()->id)
        ->click('@mobile-panel-preview')
        ->assertVisible('@preview-frame');

    $editor->assertNoJavaScriptErrors();
});
