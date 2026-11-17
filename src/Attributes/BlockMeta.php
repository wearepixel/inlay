<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class BlockMeta
{
    /**
     * @param  array<int, string>  $keywords
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
