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
});

it('shows every registered block for a page using the default, unrestricted definition', function () {
    $page = Page::create(['title' => 'Home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSeeHtml('picker-block-test-hero')
        ->assertSeeHtml('picker-block-test-faq')
        ->assertSeeHtml('picker-block-test-cta');
});

it('shows only the blocks allowed by the page\'s Page Definition, preserving category grouping', function () {
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);

    $test = Livewire::test(PageEditor::class, ['page' => $page])->call('openPicker');

    $test->assertSeeHtml('picker-block-test-hero')
        ->assertSeeHtml('picker-block-test-faq')
        ->assertDontSeeHtml('picker-block-test-cta');

    // Categories are still rendered, not flattened.
    $test->assertSee('Headers')->assertSee('Uncategorised');
});

it('shows a calm empty state when the current page type allows no further blocks', function () {
    final class NoBlocksAllowedForPickerTest extends PageDefinition
    {
        public function allowedBlocks(): array
        {
            return [];
        }
    }

    app(PageDefinitionRegistry::class)->register(NoBlocksAllowedForPickerTest::class);
    $page = Page::create(['title' => 'Locked Down', 'definition' => 'no-blocks-allowed-for-picker-test']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSee('No more sections are available for this page type')
        ->assertDontSeeHtml('picker-block-test-hero');
});

it('shows a developer-facing message when the page\'s own definition is no longer registered', function () {
    $page = Page::create(['title' => 'Orphaned', 'definition' => 'no-longer-registered']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSee('type is no longer registered')
        ->assertDontSeeHtml('picker-block-test-hero');
});
