<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Pages\PageDefinition;

/**
 * See TestLockedPositionV1's docblock. Locks (and therefore also requires)
 * the hero, which V1 left free to move.
 */
#[PageDefinitionMeta(handle: 'locked-position-page', label: 'Locked Position Page (v2)')]
final class TestLockedPositionV2 extends PageDefinition
{
    public function allowedSections(): array
    {
        return [TestHeroSection::class, TestFaqSection::class];
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
