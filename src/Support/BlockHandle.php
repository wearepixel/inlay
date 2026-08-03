<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

/**
 * A typed reference to a registered block by its handle, for contexts
 * that need to name a block but have no PHP class to name (a
 * single-file block). A class-based block can still be referenced
 * by its class-string wherever that's more natural - this exists
 * specifically for the case where there isn't one.
 *
 * Used by a Page Definition's allowedBlocks()/requiredBlocks()/
 * lockedBlocks()/defaultBlocks(), e.g. `[Hero::class,
 * BlockHandle::make('promo-banner')]` - see
 * WeArePixel\Inlay\Pages\PageDefinitionRegistry, which normalises every
 * such reference into the block's own stable registered handle and
 * rejects a bare, untyped string instead of silently accepting one.
 */
final readonly class BlockHandle
{
    private function __construct(public string $value) {}

    public static function make(string $handle): self
    {
        return new self($handle);
    }
}
