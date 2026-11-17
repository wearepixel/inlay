<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Pages\PageDefinition;

/**
 * See TestShiftingPageDefinitionV1's docblock. This "later" version now
 * requires test-faq, which a revision published under V1's looser rules
 * would not necessarily have.
 */
#[PageDefinitionMeta(handle: 'shifting-page', label: 'Shifting Page (v2)')]
final class TestShiftingPageDefinitionV2 extends PageDefinition
{
    public function allowedSections(): array
    {
        return [TestHeroSection::class, TestFaqSection::class];
    }

    public function requiredSections(): array
    {
        return [TestFaqSection::class];
    }
}
