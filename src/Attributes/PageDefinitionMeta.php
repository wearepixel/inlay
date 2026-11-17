<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Attributes;

use Attribute;

/**
 * Optional class-level metadata for a PageDefinition, in the same spirit as
 * #[SectionMeta] for a Section - a definition with no #[PageDefinitionMeta]
 * still registers normally, falling back to a handle/label derived from its
 * class name (docs/inlay/v1/page-definitions).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PageDefinitionMeta
{
    public function __construct(
        public ?string $handle = null,
        public ?string $label = null,
        public string $description = '',
        public ?string $icon = null,
    ) {}
}
