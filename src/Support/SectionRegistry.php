<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

use InvalidArgumentException;
use ReflectionClass;
use WearePixel\Inlay\Attributes\ComponentMeta;
use WearePixel\Inlay\Contracts\InlayComponent;

/**
 * Resolves a registered handle to its ComponentDefinition regardless of
 * which authoring style produced it - class-based entries come from
 * config('inlay.sections') and are resolved (and reflected) lazily via
 * ComponentDefinitionResolver on every call, exactly as before; single-file
 * entries are registered once, already fully resolved, via
 * registerSingleFile(). Nothing downstream of definition() needs to know
 * which of the two produced a given result.
 */
final class SectionRegistry
{
    /**
     * @var array<string, ComponentDefinition>
     */
    private array $singleFileDefinitions = [];

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
     * Registers an already-resolved single-file component definition. A
     * handle already claimed by a class-based component (or another
     * single-file one) is a registration error, not a silent override -
     * two components sharing a handle is exactly the ambiguity a stable,
     * unique handle exists to prevent.
     */
    public function registerSingleFile(ComponentDefinition $definition): void
    {
        if (isset($this->sections[$definition->handle])) {
            throw new InvalidArgumentException(
                "Cannot register single-file component [{$definition->handle}] - a class-based component is already registered for that handle."
            );
        }

        if (isset($this->singleFileDefinitions[$definition->handle])) {
            throw new InvalidArgumentException(
                "Cannot register single-file component [{$definition->handle}] - a single-file component is already registered for that handle."
            );
        }

        $this->singleFileDefinitions[$definition->handle] = $definition;
    }

    /**
     * The single unified lookup - resolves a handle to its
     * ComponentDefinition regardless of authoring style.
     */
    public function definition(string $slug): ComponentDefinition
    {
        if (isset($this->singleFileDefinitions[$slug])) {
            return $this->singleFileDefinitions[$slug];
        }

        $componentClass = $this->resolve($slug);
        $settings = app(ComponentDefinitionResolver::class)->resolve($componentClass);

        $attribute = (new ReflectionClass($componentClass))
            ->getAttributes(ComponentMeta::class)[0] ?? null;

        $meta = $attribute?->newInstance() ?? new ComponentMeta;

        return new ComponentDefinition(
            handle: $slug,
            name: $meta->label ?? str($slug)->headline()->toString(),
            description: $meta->description,
            category: $meta->category,
            icon: $meta->icon,
            thumbnail: $meta->thumbnail,
            sourceType: ComponentSourceType::ClassBased,
            sourceIdentifier: $componentClass,
            settings: $settings,
        );
    }

    /**
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return [...array_keys($this->sections), ...array_keys($this->singleFileDefinitions)];
    }

    /**
     * The resolved, always-complete metadata for one registered component,
     * for the admin's component picker.
     */
    public function metadata(string $slug): ComponentMetadata
    {
        return $this->definition($slug)->toMetadata();
    }

    /**
     * @return array<int, ComponentMetadata>
     */
    public function all(): array
    {
        return array_map($this->metadata(...), $this->slugs());
    }
}
