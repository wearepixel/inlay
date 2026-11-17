<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

/**
 * A typed reference to a registered section by its handle, for contexts
 * that need to name a section but have no PHP class to name (a
 * single-file section). A class-based section can still be referenced
 * by its class-string wherever that's more natural - this exists
 * specifically for the case where there isn't one.
 *
 * Used by a Page Definition's allowedSections()/requiredSections()/
 * lockedSections()/defaultSections(), e.g. `[Hero::class,
 * SectionHandle::make('promo-banner')]` - see
 * WeArePixel\Inlay\Pages\PageDefinitionRegistry, which normalises every
 * such reference into the section's own stable registered handle and
 * rejects a bare, untyped string instead of silently accepting one.
 */
final readonly class SectionHandle
{
    private function __construct(public string $value) {}

    public static function make(string $handle): self
    {
        return new self($handle);
    }
}
