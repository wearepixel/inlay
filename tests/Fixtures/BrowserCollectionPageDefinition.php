<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Pages\PageDefinition;

/**
 * A constrained Page Definition for the browser test suite - mirrors the
 * ecommerce CollectionPage example in docs/inlay/v1/page-definitions, using the
 * existing browser fixture sections so the workflow exercises a real,
 * end-to-end editor session under real guardrails.
 */
#[PageDefinitionMeta(label: 'Browser Collection Page')]
final class BrowserCollectionPageDefinition extends PageDefinition
{
    public function allowedSections(): array
    {
        return [BrowserHeroSection::class, TestFaqSection::class];
    }

    public function requiredSections(): array
    {
        return [BrowserHeroSection::class];
    }

    public function lockedSections(): array
    {
        return [BrowserHeroSection::class];
    }

    public function defaultSections(): array
    {
        return [BrowserHeroSection::class];
    }
}
