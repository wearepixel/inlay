<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Pages\PageDefinition;

/**
 * Paired with TestLockedPositionV2 - same handle, no locking here. Used to
 * isolate the "restoring a revision would move a now-locked block" check
 * from the ordinary required/allowed checks (both versions allow and, once
 * present, are satisfied by, the same blocks - only locking differs).
 */
#[PageDefinitionMeta(handle: 'locked-position-page', label: 'Locked Position Page (v1)')]
final class TestLockedPositionV1 extends PageDefinition
{
    public function allowedBlocks(): array
    {
        return [TestHeroSection::class, TestFaqSection::class];
    }
}
