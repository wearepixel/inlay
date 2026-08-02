<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Attributes;

use Attribute;

/**
 * Optional class-level metadata a developer attaches to an InlayComponent so
 * the admin's component picker can group, search, and describe it without
 * any hardcoded per-component logic - docs/v1-implementation-specification.md
 * "Page Definitions" / component registration. A component with no
 * #[ComponentMeta] still registers and renders normally; the picker falls
 * back to a name derived from its slug and an "Uncategorised" group.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ComponentMeta
{
    /**
     * @param  array<int, string>  $keywords  extra terms the component picker's search matches against, alongside the label, category, and description
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
