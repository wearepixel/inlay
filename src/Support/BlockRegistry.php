<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use InvalidArgumentException;
use ReflectionClass;
use WeArePixel\Inlay\Attributes\BlockMeta;
use WeArePixel\Inlay\Contracts\InlayBlock;

/**
 * Resolves a registered handle to its BlockDefinition regardless of
 * which authoring style produced it - class-based entries come from
 * config('inlay.blocks') and are resolved (and reflected) lazily via
 * BlockDefinitionResolver on every call, exactly as before; single-file
 * entries are registered once, already fully resolved, via
 * registerSingleFile(). Nothing downstream of definition() needs to know
 * which of the two produced a given result.
 */
final class BlockRegistry
{
    /**
     * @var array<string, BlockDefinition>
     */
    private array $singleFileDefinitions = [];

    /**
     * @param  array<string, class-string<InlayBlock>>  $blocks
     */
    public function __construct(private readonly array $blocks) {}

    public static function fromConfig(): self
    {
        return new self(config('inlay.blocks', []));
    }

    /**
     * @return class-string<InlayBlock>
     */
    public function resolve(string $slug): string
    {
        if (! isset($this->blocks[$slug])) {
            throw new InvalidArgumentException("No block registered for slug [{$slug}].");
        }

        return $this->blocks[$slug];
    }

    /**
     * Registers an already-resolved single-file block definition. A
     * handle already claimed by a class-based block (or another
     * single-file one) is a registration error, not a silent override -
     * two blocks sharing a handle is exactly the ambiguity a stable,
     * unique handle exists to prevent.
     */
    public function registerSingleFile(BlockDefinition $definition): void
    {
        if (isset($this->blocks[$definition->handle])) {
            throw new InvalidArgumentException(
                "Cannot register single-file block [{$definition->handle}] - a class-based block is already registered for that handle."
            );
        }

        if (isset($this->singleFileDefinitions[$definition->handle])) {
            throw new InvalidArgumentException(
                "Cannot register single-file block [{$definition->handle}] - a single-file block is already registered for that handle."
            );
        }

        $this->singleFileDefinitions[$definition->handle] = $definition;
    }

    /**
     * The single unified lookup - resolves a handle to its
     * BlockDefinition regardless of authoring style.
     */
    public function definition(string $slug): BlockDefinition
    {
        if (isset($this->singleFileDefinitions[$slug])) {
            return $this->singleFileDefinitions[$slug];
        }

        $blockClass = $this->resolve($slug);
        $settings = app(BlockDefinitionResolver::class)->resolve($blockClass);

        $attribute = (new ReflectionClass($blockClass))
            ->getAttributes(BlockMeta::class)[0] ?? null;

        $meta = $attribute?->newInstance() ?? new BlockMeta;

        return new BlockDefinition(
            handle: $slug,
            name: $meta->label ?? str($slug)->headline()->toString(),
            description: $meta->description,
            category: $meta->category,
            icon: $meta->icon,
            thumbnail: $meta->thumbnail,
            sourceType: BlockSourceType::ClassBased,
            sourceIdentifier: $blockClass,
            settings: $settings,
            keywords: $meta->keywords,
        );
    }

    /**
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return [...array_keys($this->blocks), ...array_keys($this->singleFileDefinitions)];
    }

    /**
     * The resolved, always-complete metadata for one registered block,
     * for the admin's block picker.
     */
    public function metadata(string $slug): BlockMetadata
    {
        return $this->definition($slug)->toMetadata();
    }

    /**
     * @return array<int, BlockMetadata>
     */
    public function all(): array
    {
        return array_map($this->metadata(...), $this->slugs());
    }
}
