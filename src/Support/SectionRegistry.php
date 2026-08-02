<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

use InvalidArgumentException;
use ReflectionClass;
use WearePixel\Inlay\Attributes\ComponentMeta;
use WearePixel\Inlay\Contracts\InlayComponent;

final class SectionRegistry
{
    /**
     * @param  array<string, class-string<InlayComponent>>  $sections
     */
    public function __construct(private readonly array $sections) {}

    public static function fromConfig(): self
    {
        return new self(config('inlay.sections', []));
    }

    /**
     * @return class-string<InlayComponent>
     */
    public function resolve(string $slug): string
    {
        if (! isset($this->sections[$slug])) {
            throw new InvalidArgumentException("No section registered for slug [{$slug}].");
        }

        return $this->sections[$slug];
    }

    /**
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return array_keys($this->sections);
    }

    /**
     * The resolved, always-complete metadata for one registered component,
     * for the admin's component picker - #[ComponentMeta] where declared,
     * a slug-derived label and "Uncategorised" category otherwise.
     */
    public function metadata(string $slug): ComponentMetadata
    {
        $componentClass = $this->resolve($slug);

        $attribute = (new ReflectionClass($componentClass))
            ->getAttributes(ComponentMeta::class)[0] ?? null;

        $meta = $attribute?->newInstance() ?? new ComponentMeta;

        return new ComponentMetadata(
            slug: $slug,
            label: $meta->label ?? str($slug)->headline()->toString(),
            category: $meta->category,
            description: $meta->description,
            icon: $meta->icon,
            thumbnail: $meta->thumbnail,
        );
    }

    /**
     * @return array<int, ComponentMetadata>
     */
    public function all(): array
    {
        return array_map($this->metadata(...), $this->slugs());
    }
}
