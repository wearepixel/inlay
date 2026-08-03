<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinition;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\SingleFile\BlockLoader;
use WeArePixel\Inlay\Support\BlockHandle;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Tests\Fixtures\TestCollectionPageDefinition;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

function makeRegistry(array $blocks = []): PageDefinitionRegistry
{
    return new PageDefinitionRegistry(new BlockRegistry($blocks));
}

it('always has the built-in, unrestricted default definition registered', function () {
    $registry = makeRegistry();

    $default = $registry->default();

    expect($default->handle)->toBe('default')
        ->and($default->isUnrestricted())->toBeTrue()
        ->and($default->requiredBlocks)->toBe([])
        ->and($default->lockedBlocks)->toBe([])
        ->and($default->defaultBlocks)->toBe([]);
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

it('normalises class-string and BlockHandle references into the same stable handles', function () {
    $registry = makeRegistry([
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]);
    $registry->register(TestCollectionPageDefinition::class);

    $resolved = $registry->definition('test-collection-page-definition');

    expect($resolved->allowedBlocks)->toContain('test-hero', 'test-faq')
        ->and($resolved->requiredBlocks)->toBe(['test-hero'])
        ->and($resolved->lockedBlocks)->toBe(['test-hero'])
        ->and($resolved->defaultBlocks)->toBe(['test-hero']);
});

it('folds required, locked, and default blocks into the effective allowed set even if a developer omits them there', function () {
    final class OmitsFromAllowedForTest extends PageDefinition
    {
        public function allowedBlocks(): array
        {
            return [];
        }

        public function requiredBlocks(): array
        {
            return [TestHeroSection::class];
        }

        public function lockedBlocks(): array
        {
            return [TestHeroSection::class];
        }
    }

    $registry = makeRegistry(['test-hero' => TestHeroSection::class]);
    $registry->register(OmitsFromAllowedForTest::class);

    $resolved = $registry->definition('omits-from-allowed-for-test');

    expect($resolved->allowedBlocks)->toBe(['test-hero']);
});

it('rejects a duplicate Page Definition handle', function () {
    $registry = makeRegistry([
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]);

    $registry->register(TestCollectionPageDefinition::class);
    $registry->register(TestCollectionPageDefinition::class);
})->throws(InvalidArgumentException::class, 'already registered under it');

it('rejects a definition referencing a block class with no registered handle', function () {
    $registry = makeRegistry(); // test-hero/test-faq deliberately not registered

    $registry->register(TestCollectionPageDefinition::class);
})->throws(InvalidArgumentException::class, 'No handle is registered for block class');

it('rejects a definition referencing a BlockHandle that is not registered', function () {
    final class UnregisteredBlockHandleForTest extends PageDefinition
    {
        public function allowedBlocks(): array
        {
            return [BlockHandle::make('does-not-exist')];
        }
    }

    $registry = makeRegistry();

    $registry->register(UnregisteredBlockHandleForTest::class);
})->throws(InvalidArgumentException::class, 'which is not registered');

it('rejects a definition that returns something other than a class-string or BlockHandle', function () {
    final class RawStringReferenceForTest extends PageDefinition
    {
        public function allowedBlocks(): array
        {
            return ['just-a-string'];
        }
    }

    $registry = makeRegistry();

    $registry->register(RawStringReferenceForTest::class);
})->throws(InvalidArgumentException::class, 'must return block class-strings implementing InlayBlock, or BlockHandle instances');

it('rejects a locked block that is not also required', function () {
    final class LockedNotRequiredForTest extends PageDefinition
    {
        public function lockedBlocks(): array
        {
            return [TestHeroSection::class];
        }
    }

    $registry = makeRegistry(['test-hero' => TestHeroSection::class]);

    $registry->register(LockedNotRequiredForTest::class);
})->throws(InvalidArgumentException::class, 'without also requiring them');

it('deduplicates repeated references in allowed/required/locked but preserves repeats in defaultBlocks', function () {
    final class RepeatedReferencesForTest extends PageDefinition
    {
        public function allowedBlocks(): array
        {
            return [TestHeroSection::class, TestHeroSection::class];
        }

        public function requiredBlocks(): array
        {
            return [TestHeroSection::class, TestHeroSection::class];
        }

        public function defaultBlocks(): array
        {
            return [TestHeroSection::class, TestHeroSection::class];
        }
    }

    $registry = makeRegistry(['test-hero' => TestHeroSection::class]);
    $registry->register(RepeatedReferencesForTest::class);

    $resolved = $registry->definition('repeated-references-for-test');

    expect($resolved->allowedBlocks)->toBe(['test-hero'])
        ->and($resolved->requiredBlocks)->toBe(['test-hero'])
        ->and($resolved->defaultBlocks)->toBe(['test-hero', 'test-hero']);
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

it('accepts a single-file block referenced via BlockHandle, not just class-based ones', function () {
    final class UsesBlockHandleForTest extends PageDefinition
    {
        public function allowedBlocks(): array
        {
            return [BlockHandle::make('single-file-hero')];
        }
    }

    $blocks = new BlockRegistry([]);
    $blocks->registerSingleFile(
        app(BlockLoader::class)->load(__DIR__.'/../Fixtures/single-file/hero.blade.php')
    );

    $registry = new PageDefinitionRegistry($blocks);
    $registry->register(UsesBlockHandleForTest::class);

    expect($registry->definition('uses-block-handle-for-test')->allowedBlocks)->toBe(['single-file-hero']);
});
