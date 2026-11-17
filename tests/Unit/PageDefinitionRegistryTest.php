<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinition;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\SingleFile\SingleFileLoader;
use WeArePixel\Inlay\Support\SectionHandle;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Tests\Fixtures\TestCollectionPageDefinition;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

function makeRegistry(array $sectionTypes = []): PageDefinitionRegistry
{
    return new PageDefinitionRegistry(new SectionRegistry($sectionTypes));
}

it('always has the built-in, unrestricted default definition registered', function () {
    $registry = makeRegistry();

    $default = $registry->default();

    expect($default->handle)->toBe('default')
        ->and($default->isUnrestricted())->toBeTrue()
        ->and($default->requiredSections)->toBe([])
        ->and($default->lockedSections)->toBe([])
        ->and($default->defaultSections)->toBe([]);
});

it('derives a handle and label from the class name when no attribute is given', function () {
    final class UnattributedPageDefinitionForTest extends PageDefinition {}

    $registry = makeRegistry();
    $registry->register(UnattributedPageDefinitionForTest::class);

    $resolved = $registry->definition('unattributed-page-definition-for-test');

    expect($resolved)->not->toBeNull()
        ->and($resolved->label)->toBe('Unattributed Page Definition For Test');
});

it('uses #[PageDefinitionMeta] overrides when given', function () {
    $registry = makeRegistry([
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]);
    $registry->register(TestCollectionPageDefinition::class);

    $resolved = $registry->definition('test-collection-page-definition');

    expect($resolved->label)->toBe('Test Collection Page')
        ->and($resolved->description)->toBe('A constrained Page type for tests.');
});

it('normalises class-string and SectionHandle references into the same stable handles', function () {
    $registry = makeRegistry([
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]);
    $registry->register(TestCollectionPageDefinition::class);

    $resolved = $registry->definition('test-collection-page-definition');

    expect($resolved->allowedSections)->toContain('test-hero', 'test-faq')
        ->and($resolved->requiredSections)->toBe(['test-hero'])
        ->and($resolved->lockedSections)->toBe(['test-hero'])
        ->and($resolved->defaultSections)->toBe(['test-hero']);
});

it('folds required, locked, and default sections into the effective allowed set even if a developer omits them there', function () {
    final class OmitsFromAllowedForTest extends PageDefinition
    {
        public function allowedSections(): array
        {
            return [];
        }

        public function requiredSections(): array
        {
            return [TestHeroSection::class];
        }

        public function lockedSections(): array
        {
            return [TestHeroSection::class];
        }
    }

    $registry = makeRegistry(['test-hero' => TestHeroSection::class]);
    $registry->register(OmitsFromAllowedForTest::class);

    $resolved = $registry->definition('omits-from-allowed-for-test');

    expect($resolved->allowedSections)->toBe(['test-hero']);
});

it('rejects a duplicate Page Definition handle', function () {
    $registry = makeRegistry([
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]);

    $registry->register(TestCollectionPageDefinition::class);
    $registry->register(TestCollectionPageDefinition::class);
})->throws(InvalidArgumentException::class, 'already registered under it');

it('rejects a definition referencing a section class with no registered handle', function () {
    $registry = makeRegistry(); // test-hero/test-faq deliberately not registered

    $registry->register(TestCollectionPageDefinition::class);
})->throws(InvalidArgumentException::class, 'No handle is registered for section class');

it('rejects a definition referencing a SectionHandle that is not registered', function () {
    final class UnregisteredSectionHandleForTest extends PageDefinition
    {
        public function allowedSections(): array
        {
            return [SectionHandle::make('does-not-exist')];
        }
    }

    $registry = makeRegistry();

    $registry->register(UnregisteredSectionHandleForTest::class);
})->throws(InvalidArgumentException::class, 'which is not registered');

it('rejects a definition that returns something other than a class-string or SectionHandle', function () {
    final class RawStringReferenceForTest extends PageDefinition
    {
        public function allowedSections(): array
        {
            return ['just-a-string'];
        }
    }

    $registry = makeRegistry();

    $registry->register(RawStringReferenceForTest::class);
})->throws(InvalidArgumentException::class, 'must return section class-strings implementing InlaySection, or SectionHandle instances');

it('rejects a locked section that is not also required', function () {
    final class LockedNotRequiredForTest extends PageDefinition
    {
        public function lockedSections(): array
        {
            return [TestHeroSection::class];
        }
    }

    $registry = makeRegistry(['test-hero' => TestHeroSection::class]);

    $registry->register(LockedNotRequiredForTest::class);
})->throws(InvalidArgumentException::class, 'without also requiring them');

it('deduplicates repeated references in allowed/required/locked but preserves repeats in defaultSections', function () {
    final class RepeatedReferencesForTest extends PageDefinition
    {
        public function allowedSections(): array
        {
            return [TestHeroSection::class, TestHeroSection::class];
        }

        public function requiredSections(): array
        {
            return [TestHeroSection::class, TestHeroSection::class];
        }

        public function defaultSections(): array
        {
            return [TestHeroSection::class, TestHeroSection::class];
        }
    }

    $registry = makeRegistry(['test-hero' => TestHeroSection::class]);
    $registry->register(RepeatedReferencesForTest::class);

    $resolved = $registry->definition('repeated-references-for-test');

    expect($resolved->allowedSections)->toBe(['test-hero'])
        ->and($resolved->requiredSections)->toBe(['test-hero'])
        ->and($resolved->defaultSections)->toBe(['test-hero', 'test-hero']);
});

it('resolves a Page with no definition column value to the built-in default', function () {
    $registry = makeRegistry();

    $page = new Page(['title' => 'Untyped']);

    expect($registry->resolveForPage($page)?->handle)->toBe('default');
});

it('returns null for a Page pointing at a definition handle that is no longer registered', function () {
    $registry = makeRegistry();

    $page = new Page(['title' => 'Orphaned', 'definition' => 'no-longer-registered']);

    expect($registry->resolveForPage($page))->toBeNull();
});

it('accepts a single-file section referenced via SectionHandle, not just class-based ones', function () {
    final class UsesSectionHandleForTest extends PageDefinition
    {
        public function allowedSections(): array
        {
            return [SectionHandle::make('single-file-hero')];
        }
    }

    $sectionTypes = new SectionRegistry([]);
    $sectionTypes->registerSingleFile(
        app(SingleFileLoader::class)->load(__DIR__.'/../Fixtures/single-file/hero.blade.php')
    );

    $registry = new PageDefinitionRegistry($sectionTypes);
    $registry->register(UsesSectionHandleForTest::class);

    expect($registry->definition('uses-section-handle-for-test')->allowedSections)->toBe(['single-file-hero']);
});
