<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Pages\PageDefinition;

#[PageDefinitionMeta(label: 'Test Collection Page', description: 'A constrained Page type for tests.')]
final class TestCollectionPageDefinition extends PageDefinition
{
    public function allowedBlocks(): array
    {
        return [
            TestHeroSection::class,
            TestFaqSection::class,
        ];
    }

    public function requiredBlocks(): array
    {
        return [
            TestHeroSection::class,
        ];
    }

    public function lockedBlocks(): array
    {
        return [
            TestHeroSection::class,
        ];
    }

    public function defaultBlocks(): array
    {
        return [
            TestHeroSection::class,
        ];
    }
}
