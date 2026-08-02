<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Attributes;

use Attribute;

/**
 * Optional class-level metadata a developer attaches to an InlayBlock so
 * the admin's block picker can group, search, and describe it without
 * any hardcoded per-block logic - docs/v1-implementation-specification.md
 * "Page Definitions" / block registration. A block with no
 * #[BlockMeta] still registers and renders normally; the picker falls
 * back to a name derived from its slug and an "Uncategorised" group.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class BlockMeta
{
    /**
     * @param  array<int, string>  $keywords  extra terms the block picker's search matches against, alongside the label, category, and description
     */
    public function __construct(
        public ?string $label = null,
        public string $category = 'Uncategorised',
        public string $description = '',
        public ?string $icon = null,
        public ?string $thumbnail = null,
        public array $keywords = [],
    ) {}
}
