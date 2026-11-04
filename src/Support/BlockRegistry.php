<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use Closure;
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
     * @var (Closure(): array<string, BlockDefinition>)|null
     */
    private ?Closure $declarativeLoader = null;

    /**
     * @var array<string, BlockDefinition>|null
     */
    private ?array $declarativeDefinitions = null;

    /**
     * @var (Closure(): int)|null
     */
    private ?Closure $declarativeVersion = null;

    private ?int $loadedVersion = null;

    private float $versionCheckedAt = 0.0;

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
    /**
     * Agent-authored blocks, loaded on first use. A developer-registered block
     * with the same handle always wins.
     *
     * $version reports a shared counter bumped on every save or delete, so a
     * long-lived process (a queue worker, Octane, a stdio MCP server) picks up
     * changes made elsewhere. It is checked at most once a second.
     *
     * @param  Closure(): array<string, BlockDefinition>  $loader
     * @param  (Closure(): int)|null  $version
     */
    public function useDeclarativeLoader(Closure $loader, ?Closure $version = null): void
    {
        $this->declarativeLoader = $loader;
        $this->declarativeVersion = $version;
        $this->declarativeDefinitions = null;
    }

    /**
     * Makes an unsaved agent-authored block resolvable for the rest of this
     * request, so it can be previewed before it is saved.
     */
    public function registerDraft(BlockDefinition $definition): void
    {
        if (isset($this->blocks[$definition->handle]) || isset($this->singleFileDefinitions[$definition->handle])) {
            return;
        }

        $this->declarativeDefinitions = [...$this->declarative(), $definition->handle => $definition];
    }

    public function forgetDeclarative(): void
    {
        $this->declarativeDefinitions = null;
    }

    public function definition(string $slug): BlockDefinition
    {
        if (isset($this->singleFileDefinitions[$slug])) {
            return $this->singleFileDefinitions[$slug];
        }

        if (! isset($this->blocks[$slug]) && isset($this->declarative()[$slug])) {
            return $this->declarative()[$slug];
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
        $codeHandles = [...array_keys($this->blocks), ...array_keys($this->singleFileDefinitions)];

        return [...$codeHandles, ...array_values(array_diff(array_keys($this->declarative()), $codeHandles))];
    }

    public function hasHandle(string $handle): bool
    {
        return isset($this->blocks[$handle]) || isset($this->singleFileDefinitions[$handle]) || isset($this->declarative()[$handle]);
    }

    /**
     * Reverse lookup: the handle a class-based block is registered under -
     * used to normalise a Page Definition's `Hero::class`-style references
     * into the stable handle actually stored on a PageSection
     * (docs/inlay/v1/page-definitions). A single-file block has no class to look
     * up this way; reference it via BlockHandle::make() instead.
     *
     * @param  class-string<InlayBlock>  $class
     */
    public function handleForClass(string $class): string
    {
        $handle = array_search($class, $this->blocks, strict: true);

        if ($handle === false) {
            throw new InvalidArgumentException(
                "No handle is registered for block class [{$class}] in config('inlay.blocks')."
            );
        }

        return $handle;
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

    /**
     * @return array<string, BlockDefinition>
     */
    private function declarative(): array
    {
        if ($this->declarativeLoader === null) {
            return [];
        }

        if ($this->declarativeDefinitions !== null && $this->declarativeVersion !== null && (float) now()->format('U.u') - $this->versionCheckedAt >= 1.0) {
            $this->versionCheckedAt = (float) now()->format('U.u');

            if (($this->declarativeVersion)() !== $this->loadedVersion) {
                $this->declarativeDefinitions = null;
            }
        }

        if ($this->declarativeDefinitions === null) {
            $this->loadedVersion = $this->declarativeVersion !== null ? ($this->declarativeVersion)() : null;
            $this->versionCheckedAt = (float) now()->format('U.u');
            $this->declarativeDefinitions = ($this->declarativeLoader)();
        }

        return $this->declarativeDefinitions;
    }
}
