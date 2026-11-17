<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

/**
 * The resolved, always-complete metadata for one registered section -
 * either declared via #[SectionMeta] or defaulted from its slug, for the
 * admin's section picker.
 */
final readonly class DefinitionMetadata
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
     * Every term the section picker's search matches against.
     *
     * @return array<int, string>
     */
    public function searchTerms(): array
    {
        return [$this->label, $this->description, $this->category, ...$this->keywords];
    }
}
