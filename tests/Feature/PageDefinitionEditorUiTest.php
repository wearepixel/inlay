<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinition;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Tests\Fixtures\TestCollectionPageDefinition;
use WeArePixel\Inlay\Tests\Fixtures\TestCtaSection;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
        'test-cta' => TestCtaSection::class,
    ]]);
    // Required + locked + default: test-hero. Allowed: test-hero, test-faq.
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);
});

it('shows a locked indicator and no enabled delete control for a locked block', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSeeHtml("locked-{$section->id}")
        ->assertSeeHtml("delete-disabled-{$section->id}")
        ->assertDontSeeHtml("delete-{$section->id}\"");
});

it('shows a required indicator for a required, not-locked block', function () {
    final class RequiredNotLockedForUiTest extends PageDefinition
    {
        public function allowedBlocks(): array
        {
            return [TestHeroSection::class];
        }

        public function requiredBlocks(): array
        {
            return [TestHeroSection::class];
        }
    }

    app(PageDefinitionRegistry::class)->register(RequiredNotLockedForUiTest::class);
    $page = Page::create(['title' => 'Landing', 'definition' => 'required-not-locked-for-ui-test']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSeeHtml("required-{$section->id}")
        ->assertSeeHtml("delete-disabled-{$section->id}"); // final instance of a required block
});

it('shows the standing violation banner with the actual violation message before Publish is even clicked', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    // Required block (test-hero) missing entirely.
    $page->sections()->create(['handle' => 'test-faq', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSeeHtml('violation-banner')
        ->assertSeeHtml('violation-missing_required_block')
        ->assertSee('is required by');
});

it('shows no violation banner for a page that satisfies its definition', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertDontSeeHtml('violation-banner');
});

it('shows the resolved Page type in Page Settings', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSee('Test Collection Page');
});

it('never changes the Page Definition when saving Page Settings, since it is immutable after creation', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->set('pageTitle', 'Renamed Landing')
        ->call('savePageSettings');

    expect($page->fresh()->definition)->toBe('test-collection-page-definition');
});
