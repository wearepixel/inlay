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
 * Intended for a future Page Definition's allowedBlocks()-style API,
 * e.g. `[Hero::class, BlockHandle::make('promo-banner')]` - Page
 * Definitions themselves are not implemented yet, so nothing currently
 * consumes this, but the handle-vs-raw-string safety/IDE-discoverability
 * decision is made now rather than left for that feature to invent later.
 */
final readonly class BlockHandle
{
    private function __construct(public string $value) {}

    public static function make(string $handle): self
    {
        return new self($handle);
    }
}
