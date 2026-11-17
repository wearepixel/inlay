<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Tests\Fixtures\BrowserCollectionPageDefinition;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;
use WeArePixel\Inlay\Tests\Fixtures\TestCtaSection;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;

/**
 * End-to-end coverage for a constrained Page Definition, in a real browser -
 * complementary to the exhaustive Feature-level coverage in
 * PageDefinitionGuardrailsTest/PageDefinitionValidatorTest/
 * PageDefinitionEditorUiTest, which exercise the same rules without a real
 * DOM. Restoring a revision that no longer satisfies today's rules (a
 * Page Definition's own code changing between two deploys) is deliberately
 * NOT re-verified here - RestorePageRevisionTest already covers it
 * precisely, and authentically reproducing "the definition's rules changed"
 * needs swapping the bound PageDefinitionRegistry mid-test, which is a
 * meaningfully different (and much more fragile) thing to do against a
 * real browser session than against an in-process Livewire test.
 */
beforeEach(function () {
    config(['inlay.sections' => [
        'hero' => BrowserHeroSection::class,
        'faq' => TestFaqSection::class,
        'cta' => TestCtaSection::class, // registered, but NOT allowed by the definition below
    ]]);
    app(PageDefinitionRegistry::class)->register(BrowserCollectionPageDefinition::class);
});

it('creates a page from a constrained Page type, seeding its default section and filtering the picker', function () {
    $visit = visit('/inlay/pages/create')
        ->fill('#page-title', 'Landing')
        ->select('#page-definition', 'browser-collection-page-definition')
        ->press('Create page')
        ->wait(1);

    // Default section (hero) was seeded automatically.
    $page = Page::sole();
    expect($page->definition)->toBe('browser-collection-page-definition')
        ->and($page->sections()->pluck('handle')->all())->toBe(['hero']);

    $visit->assertSee('Hero');

    // The picker shows only what this Page type allows.
    $visit->click('@open-picker')
        ->assertVisible('@picker-section-faq')
        ->assertMissing('@picker-section-cta')
        ->assertNoJavaScriptErrors();
});

it('shows the locked indicator and prevents removing or keyboard-reordering the locked default section', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'browser-collection-page-definition']);
    $locked = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);
    $faq = $page->sections()->create(['handle' => 'faq', 'order' => 1, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->assertSee('Locked');

    // The delete control for the locked row is disabled, not merely hidden.
    $editor->assertPresent("[data-test='delete-disabled-{$locked->id}']")
        ->assertMissing("[data-test='delete-{$locked->id}']");

    // Alt+ArrowDown is the documented keyboard reorder shortcut - it must
    // not move the locked row even when focused directly.
    $editor->keys("[data-section-id=\"{$locked->id}\"]", 'Alt+ArrowDown')->wait(1);

    expect($locked->fresh()->order)->toBe(0)
        ->and($faq->fresh()->order)->toBe(1);

    $editor->assertNoJavaScriptErrors();
});

it('sections publishing an invalid page and allows it once the violation is corrected', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'browser-collection-page-definition']);
    // Required section (hero) is missing entirely - created with none, unlike
    // the create-page flow which would have seeded it.

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->assertSee("can't be published yet")
        ->assertSee('is required by');

    $editor->click('@publish')->wait(1);

    expect($page->fresh()->status->value)->toBe('draft');
    $editor->assertSee("can't be published yet");

    $editor->click('@open-picker')->click('@picker-section-hero')->wait(1);

    $editor->assertMissing('@violation-banner')
        ->click('@publish')
        ->wait(1)
        ->assertSee('Published successfully');

    expect($page->fresh()->status->value)->toBe('published');

    $editor->assertNoJavaScriptErrors();
});
