<?php

declare(strict_types=1);

use WearePixel\Inlay\Enums\PageStatus;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\BrowserHeroSection;
use WearePixel\Inlay\Tests\Fixtures\TestFaqSection;

/**
 * Exercises the complete editorial workflow end to end in a real browser,
 * split into smaller, independently-debuggable tests rather than one long
 * chain. Two things are deliberately NOT covered here:
 *
 * - Native HTML5 drag-and-drop reordering: WebDriver/CDP drag simulation is
 *   unreliable, so the reorder *behaviour* is proven at the Livewire level
 *   in tests/Feature/PageEditorTest.php ("it persists drag-reordering").
 *   The test below only proves the drag UI itself is wired up.
 * - Locked/required Component enforcement (Page Definitions): that
 *   subsystem does not exist in this release - see the completion report.
 */
beforeEach(function () {
    config(['inlay.sections' => [
        'hero' => BrowserHeroSection::class,
        'faq' => TestFaqSection::class,
    ]]);
});

it('creates a page from the index and opens its editor', function () {
    visit('/inlay/pages')
        ->assertSee('Pages')
        ->assertSee('No pages yet')
        ->click('New page')
        ->fill('#page-title', 'Home')
        ->assertValue('#page-slug', 'home')
        ->press('Create page')
        // Livewire's redirectRoute() after save() lands here.
        ->assertSee('Home')
        ->assertSee('No components yet.');

    expect(Page::sole())
        ->title->toBe('Home')
        ->slug->toBe('home')
        ->status->toBe(PageStatus::Draft);
});

it('opens the component picker, searches, and adds a component grouped by category', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click('@open-picker')
        ->assertVisible('@picker-search')
        ->assertSee('Headers') // BrowserHeroSection's #[ComponentMeta] category
        ->assertSee('Uncategorised'); // TestFaqSection has no #[ComponentMeta]

    $editor->fill('@picker-search', 'Hero')
        ->assertVisible('@picker-card-hero')
        ->assertMissing('@picker-card-faq');

    $editor->click('@picker-card-hero')->assertMissing('@picker-search');

    $section = $page->sections()->sole();
    expect($section->handle)->toBe('hero');
});

it('selects a component, edits a setting, and shows conditional visibility', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click("@tree-item-{$section->id}")
        ->assertValue('@setting-heading', 'Welcome');

    $editor->clear('@setting-heading')
        ->type('@setting-heading', 'Welcome to Acme')
        ->wait(1); // 400ms debounce + a Livewire round trip

    expect($section->fresh()->settings)->toBe(['heading' => 'Welcome to Acme']);

    $editor->assertMissing('@setting-ctaLabel')
        ->check('@setting-showCta')
        ->assertVisible('@setting-ctaLabel')
        ->uncheck('@setting-showCta')
        ->assertMissing('@setting-ctaLabel');
});

it('duplicates, shows drag connectivity, and deletes a component', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    // wire:confirm (used by delete, below) shows a native confirm() dialog
    // that this plugin's API cannot intercept directly - overriding
    // window.confirm is the standard technique for headless-testing
    // confirm-gated UI.
    $editor->script('window.confirm = () => true;');

    // The duplicate/delete icons are only revealed on :hover, so a real
    // interaction hovers the row first.
    $editor->hover("@tree-item-{$section->id}")
        ->click("@duplicate-{$section->id}")
        ->wait(1);

    expect($page->sections()->count())->toBe(2);
    $duplicate = $page->sections()->where('id', '!=', $section->id)->sole();

    // Proves the drag UI is connected (both rows are draggable handles
    // wired to the same Alpine component that calls $wire.updateOrder()) -
    // see the class docblock for why the drag gesture itself isn't
    // simulated here.
    $editor->assertAttribute("@tree-item-{$section->id}", 'draggable', 'true')
        ->assertAttribute("@tree-item-{$duplicate->id}", 'draggable', 'true');

    $editor->hover("@tree-item-{$duplicate->id}")
        ->click("@delete-{$duplicate->id}")
        ->wait(1);

    expect($page->sections()->count())->toBe(1);
    $editor->assertMissing("@tree-item-{$duplicate->id}");
});

it('reloads the preview iframe with the current draft content', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => ['heading' => 'Hi there']]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->withinFrame('[data-test="preview-frame"]', function ($frame): void {
        $frame->assertSee('Hi there');
    });
});

it('publishes, diverges draft from published output, republishes, and restores an earlier revision', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => ['heading' => 'Welcome to Acme']]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click('@publish')->wait(1)->assertSee('Published');

    expect($page->fresh()->status)->toBe(PageStatus::Published)
        ->and($page->revisions()->count())->toBe(1);

    $firstRevision = $page->revisions()->sole();

    // Changing the draft must not change what's already published.
    visit("/site/{$page->id}")->assertSee('Welcome to Acme');

    $editor->click("@tree-item-{$section->id}")
        ->clear('@setting-heading')
        ->type('@setting-heading', 'Changed draft only')
        ->wait(1);

    visit("/site/{$page->id}")
        ->assertSee('Welcome to Acme')
        ->assertDontSee('Changed draft only');

    // Publishing again creates a new revision and updates public output.
    $editor->click('@publish')->wait(1);

    expect($page->revisions()->count())->toBe(2);
    visit("/site/{$page->id}")->assertSee('Changed draft only');

    // Restoring an earlier revision replaces the live sections (with fresh
    // row ids - $section's original id no longer exists) and immediately
    // publishes a new revision from it, per
    // tests/Feature/PageEditorHistoryTest.php.
    $editor->click('@toggle-history')
        ->assertVisible("@restore-{$firstRevision->id}")
        ->click("@restore-{$firstRevision->id}")
        ->wait(1);

    // A published snapshot stores fully-resolved settings for every field,
    // not just the sparse override that was live at the time - restoring
    // writes that fully-resolved snapshot back verbatim.
    expect($page->sections()->sole()->settings)->toBe([
        'heading' => 'Welcome to Acme',
        'showCta' => false,
        'ctaLabel' => 'Learn more',
    ])->and($page->revisions()->count())->toBe(3);

    visit("/site/{$page->id}")->assertSee('Welcome to Acme');
});
