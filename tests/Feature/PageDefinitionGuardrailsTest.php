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
    // Required + locked + default: test-hero. Allowed: test-hero, test-faq.
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);
});

it('rejects adding a section that is not allowed by the current Page Definition, even via a direct call bypassing the picker', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'test-cta')
        ->assertOk();

    expect($page->sections()->count())->toBe(0);
});

it('allows adding a section the current Page Definition does allow', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'test-faq')
        ->assertOk();

    expect($page->sections()->count())->toBe(1);
});

it('rejects deleting a locked section instance', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('deleteSection', $section->id)
        ->assertOk();

    expect($page->sections()->count())->toBe(1);
});

it('allows deleting a required section instance when another instance of the same section remains', function () {
    final class RequiredNotLockedForGuardrailsTest extends PageDefinition
    {
        public function allowedSections(): array
        {
            return [TestHeroSection::class];
        }

        public function requiredSections(): array
        {
            return [TestHeroSection::class];
        }
    }

    app(PageDefinitionRegistry::class)->register(RequiredNotLockedForGuardrailsTest::class);

    $page = Page::create(['title' => 'Two Heroes', 'definition' => 'required-not-locked-for-guardrails-test']);
    $first = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 1, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('deleteSection', $first->id)
        ->assertOk();

    expect($page->sections()->count())->toBe(1);
});

it('rejects the final required instance under the same not-locked definition', function () {
    final class RequiredNotLockedForGuardrailsTest2 extends PageDefinition
    {
        public function allowedSections(): array
        {
            return [TestHeroSection::class];
        }

        public function requiredSections(): array
        {
            return [TestHeroSection::class];
        }
    }

    app(PageDefinitionRegistry::class)->register(RequiredNotLockedForGuardrailsTest2::class);

    $page = Page::create(['title' => 'One Hero', 'definition' => 'required-not-locked-for-guardrails-test2']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('deleteSection', $section->id)
        ->assertOk();

    expect($page->sections()->count())->toBe(1);
});

it('rejects a reorder that would move a locked section, applying none of it', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $locked = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $faq = $page->sections()->create(['handle' => 'test-faq', 'order' => 1, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('updateOrder', [$faq->id, $locked->id])
        ->assertOk();

    expect($locked->fresh()->order)->toBe(0)
        ->and($faq->fresh()->order)->toBe(1);
});

it('allows a reorder that never changes a locked section\'s position', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $locked = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $faqA = $page->sections()->create(['handle' => 'test-faq', 'order' => 1, 'settings' => []]);
    $faqB = $page->sections()->create(['handle' => 'test-faq', 'order' => 2, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('updateOrder', [$locked->id, $faqB->id, $faqA->id])
        ->assertOk();

    expect($locked->fresh()->order)->toBe(0)
        ->and($faqB->fresh()->order)->toBe(1)
        ->and($faqA->fresh()->order)->toBe(2);
});

it('rejects duplicating a section the current Page Definition does not allow', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $section = $page->sections()->create(['handle' => 'test-cta', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('duplicateSection', $section->id)
        ->assertOk();

    expect($page->sections()->count())->toBe(1);
});

it('allows duplicating a locked section, producing another locked instance under the type-level rule', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('duplicateSection', $section->id)
        ->assertOk();

    expect($page->sections()->count())->toBe(2)
        ->and($page->sections()->pluck('handle')->unique()->all())->toBe(['test-hero']);
});

it('de-duplicates a crafted id array before checking or applying a reorder, rather than letting a repeated id\'s later occurrence silently win', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $locked = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $faqA = $page->sections()->create(['handle' => 'test-faq', 'order' => 1, 'settings' => []]);
    $faqB = $page->sections()->create(['handle' => 'test-faq', 'order' => 2, 'settings' => []]);

    // Without de-duplicating first, [locked, faqB, locked, faqA] would
    // still look like a no-op for the locked id under a naive
    // array_search() comparison (its *first* occurrence sits at index 0
    // either way), while the write loop's later, second occurrence of the
    // same id would silently overwrite its order a second time - moving it
    // without the position check ever seeing it move. De-duplicating
    // first (keeping only the first occurrence) makes the array reflect
    // what will actually be written, so the locked id's real, final
    // position is exactly what gets checked.
    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('updateOrder', [$locked->id, $faqB->id, $locked->id, $faqA->id])
        ->assertOk();

    expect($locked->fresh()->order)->toBe(0)
        ->and($faqB->fresh()->order)->toBe(1)
        ->and($faqA->fresh()->order)->toBe(2);
});

it('rejects a reorder that omits sections, rather than reindexing the listed subset into colliding order values', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $locked = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $faqA = $page->sections()->create(['handle' => 'test-faq', 'order' => 1, 'settings' => []]);
    $faqB = $page->sections()->create(['handle' => 'test-faq', 'order' => 2, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('updateOrder', [$faqB->id])
        ->assertOk();

    expect($locked->fresh()->order)->toBe(0)
        ->and($faqA->fresh()->order)->toBe(1)
        ->and($faqB->fresh()->order)->toBe(2);
});

it('rejects deleting and reordering when the page\'s own Page Definition no longer resolves, rather than allowing everything', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'no-longer-registered']);
    $first = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $second = $page->sections()->create(['handle' => 'test-faq', 'order' => 1, 'settings' => []]);

    $test = Livewire::test(PageEditor::class, ['page' => $page]);

    $test->call('deleteSection', $first->id)->assertOk();
    expect($page->sections()->count())->toBe(2);

    $test->call('updateOrder', [$second->id, $first->id])->assertOk();
    expect($first->fresh()->order)->toBe(0)
        ->and($second->fresh()->order)->toBe(1);
});

it('rejects adding or duplicating an unregistered section handle instead of throwing', function () {
    $page = Page::create(['title' => 'Landing']); // default, unrestricted definition

    $section = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    $test = Livewire::test(PageEditor::class, ['page' => $page]);

    $test->call('addSection', 'not-a-real-section')->assertOk();
    expect($page->sections()->count())->toBe(1);

    // Simulates a section deregistered after this section was created.
    $section->update(['handle' => 'not-a-real-section']);
    $test->call('duplicateSection', $section->id)->assertOk();
    expect($page->sections()->count())->toBe(1);
});

it('renders without error and allows deleting a section whose section is no longer registered', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $required = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);
    $orphaned = $page->sections()->create(['handle' => 'no-longer-registered-section', 'order' => 1, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertOk()
        ->assertSeeHtml('No longer registered')
        ->call('deleteSection', $orphaned->id)
        ->assertOk();

    expect($page->sections()->pluck('id')->all())->toBe([$required->id]);
});
