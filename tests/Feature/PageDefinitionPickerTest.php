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
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
        'test-cta' => TestCtaSection::class,
    ]]);
});

it('shows every registered section for a page using the default, unrestricted definition', function () {
    $page = Page::create(['title' => 'Home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSeeHtml('picker-section-test-hero')
        ->assertSeeHtml('picker-section-test-faq')
        ->assertSeeHtml('picker-section-test-cta');
});

it('shows only the sections allowed by the page\'s Page Definition, preserving category grouping', function () {
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);

    $test = Livewire::test(PageEditor::class, ['page' => $page])->call('openPicker');

    $test->assertSeeHtml('picker-section-test-hero')
        ->assertSeeHtml('picker-section-test-faq')
        ->assertDontSeeHtml('picker-section-test-cta');

    // Categories are still rendered, not flattened.
    $test->assertSee('Headers')->assertSee('Uncategorised');
});

it('shows a calm empty state when the current page type allows no further sections', function () {
    final class NoSectionsAllowedForPickerTest extends PageDefinition
    {
        public function allowedSections(): array
        {
            return [];
        }
    }

    app(PageDefinitionRegistry::class)->register(NoSectionsAllowedForPickerTest::class);
    $page = Page::create(['title' => 'Locked Down', 'definition' => 'no-sections-allowed-for-picker-test']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSee('No more sections are available for this page type')
        ->assertDontSeeHtml('picker-section-test-hero');
});

it('shows a developer-facing message when the page\'s own definition is no longer registered', function () {
    $page = Page::create(['title' => 'Orphaned', 'definition' => 'no-longer-registered']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSee('type is no longer registered')
        ->assertDontSeeHtml('picker-section-test-hero');
});
