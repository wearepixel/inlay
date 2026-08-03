<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Pages\PageDefinition;

/**
 * Paired with TestShiftingPageDefinitionV2 - both register under the same
 * explicit handle, simulating a developer changing a Page Definition's own
 * rules between two deploys (the Page's `definition` column never changes;
 * only which rules that handle currently maps to does) for
 * RestorePageRevisionTest's "an old revision no longer satisfies today's
 * rules" scenario.
 */
#[PageDefinitionMeta(handle: 'shifting-page', label: 'Shifting Page (v1)')]
final class TestShiftingPageDefinitionV1 extends PageDefinition
{
    public function allowedBlocks(): array
    {
        return [TestHeroSection::class, TestFaqSection::class];
    }
}
