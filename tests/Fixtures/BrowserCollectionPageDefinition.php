<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Pages\PageDefinition;

/**
 * A constrained Page Definition for the browser test suite - mirrors the
 * ecommerce CollectionPage example in docs/page-definitions.md, using the
 * existing browser fixture blocks so the workflow exercises a real,
 * end-to-end editor session under real guardrails.
 */
#[PageDefinitionMeta(label: 'Browser Collection Page')]
final class BrowserCollectionPageDefinition extends PageDefinition
{
    public function allowedBlocks(): array
    {
        return [BrowserHeroSection::class, TestFaqSection::class];
    }

    public function requiredBlocks(): array
    {
        return [BrowserHeroSection::class];
    }

    public function lockedBlocks(): array
    {
        return [BrowserHeroSection::class];
    }

    public function defaultBlocks(): array
    {
        return [BrowserHeroSection::class];
    }
}
