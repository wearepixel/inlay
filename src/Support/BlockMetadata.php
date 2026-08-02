<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

/**
 * The resolved, always-complete metadata for one registered block -
 * either declared via #[BlockMeta] or defaulted from its slug, for the
 * admin's block picker.
 */
final readonly class BlockMetadata
{
    /**
     * @param  array<int, string>  $keywords
     */
    public function __construct(
        public string $slug,
        public string $label,
        public string $category,
        public string $description,
        public ?string $icon,
        public ?string $thumbnail,
        public array $keywords = [],
    ) {}

    /**
     * Every term the block picker's search matches against.
     *
     * @return array<int, string>
     */
    public function searchTerms(): array
    {
        return [$this->label, $this->description, $this->category, ...$this->keywords];
    }
}
