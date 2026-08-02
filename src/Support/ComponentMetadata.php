<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

/**
 * The resolved, always-complete metadata for one registered component -
 * either declared via #[ComponentMeta] or defaulted from its slug, for the
 * admin's component picker.
 */
final readonly class ComponentMetadata
{
    public function __construct(
        public string $slug,
        public string $label,
        public string $category,
        public string $description,
        public ?string $icon,
        public ?string $thumbnail,
    ) {}
}
