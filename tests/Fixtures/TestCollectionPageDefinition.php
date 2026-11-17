<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Pages\PageDefinition;

#[PageDefinitionMeta(label: 'Test Collection Page', description: 'A constrained Page type for tests.')]
final class TestCollectionPageDefinition extends PageDefinition
{
    public function allowedSections(): array
    {
        return [
            TestHeroSection::class,
            TestFaqSection::class,
        ];
    }

    public function requiredSections(): array
    {
        return [
            TestHeroSection::class,
        ];
    }

    public function lockedSections(): array
    {
        return [
            TestHeroSection::class,
        ];
    }

    public function defaultSections(): array
    {
        return [
            TestHeroSection::class,
        ];
    }
}
