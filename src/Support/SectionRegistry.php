<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use WeArePixel\Inlay\Attributes\SectionMeta;
use WeArePixel\Inlay\Contracts\InlaySection;

/**
 * Resolves a registered handle to its Definition regardless of
 * which authoring style produced it - class-based entries come from
 * config('inlay.sections') and are resolved (and reflected) lazily via
 * DefinitionResolver on every call, exactly as before; single-file
 * entries are registered once, already fully resolved, via
 * registerSingleFile(). Nothing downstream of definition() needs to know
 * which of the two produced a given result.
 */
final class SectionRegistry
{
    /**
     * @var array<string, Definition>
     */
    private array $singleFileDefinitions = [];

    /**
     * @var (Closure(): array<string, Definition>)|null
     */
    private ?Closure $declarativeLoader = null;

    /**
     * @var array<string, Definition>|null
     */
    private ?array $declarativeDefinitions = null;

    /**
     * @var (Closure(): int)|null
     */
    private ?Closure $declarativeVersion = null;

    private ?int $loadedVersion = null;

    private float $versionCheckedAt = 0.0;

    /**
     * @param  array<string, class-string<InlaySection>>  $sections
     */
    public function __construct(private readonly array $sections) {}

    public static function fromConfig(): self
    {
        return new self(config('inlay.sections', []));
    }

    /**
     * @return class-string<InlaySection>
     */
    public function resolve(string $slug): string
    {
        if (! isset($this->sections[$slug])) {
            throw new InvalidArgumentException("No section registered for slug [{$slug}].");
        }

        return $this->sections[$slug];
    }

    /**
     * Registers an already-resolved single-file section definition. A
     * handle already claimed by a class-based section (or another
     * single-file one) is a registration error, not a silent override -
     * two sections sharing a handle is exactly the ambiguity a stable,
     * unique handle exists to prevent.
     */
    public function registerSingleFile(Definition $definition): void
    {
        if (isset($this->sections[$definition->handle])) {
            throw new InvalidArgumentException(
                "Cannot register single-file section [{$definition->handle}] - a class-based section is already registered for that handle."
            );
        }

        if (isset($this->singleFileDefinitions[$definition->handle])) {
            throw new InvalidArgumentException(
                "Cannot register single-file section [{$definition->handle}] - a single-file section is already registered for that handle."
            );
        }

        $this->singleFileDefinitions[$definition->handle] = $definition;
    }

    /**
     * The single unified lookup - resolves a handle to its
     * Definition regardless of authoring style.
     */
    /**
     * Agent-authored sections, loaded on first use. A developer-registered section
     * with the same handle always wins.
     *
     * $version reports a shared counter bumped on every save or delete, so a
     * long-lived process (a queue worker, Octane, a stdio MCP server) picks up
     * changes made elsewhere. It is checked at most once a second.
     *
     * @param  Closure(): array<string, Definition>  $loader
     * @param  (Closure(): int)|null  $version
     */
    public function useDeclarativeLoader(Closure $loader, ?Closure $version = null): void
    {
        $this->declarativeLoader = $loader;
        $this->declarativeVersion = $version;
        $this->declarativeDefinitions = null;
    }

    /**
     * Makes an unsaved agent-authored section resolvable for the rest of this
     * request, so it can be previewed before it is saved.
     */
    public function registerDraft(Definition $definition): void
    {
        if (isset($this->sections[$definition->handle]) || isset($this->singleFileDefinitions[$definition->handle])) {
            return;
        }

        $this->declarativeDefinitions = [...$this->declarative(), $definition->handle => $definition];
    }

    public function forgetDeclarative(): void
    {
        $this->declarativeDefinitions = null;
    }

    public function definition(string $slug): Definition
    {
        if (isset($this->singleFileDefinitions[$slug])) {
            return $this->singleFileDefinitions[$slug];
        }

        if (! isset($this->sections[$slug]) && isset($this->declarative()[$slug])) {
            return $this->declarative()[$slug];
        }

        $sectionClass = $this->resolve($slug);
        $settings = app(DefinitionResolver::class)->resolve($sectionClass);

        $attribute = (new ReflectionClass($sectionClass))
            ->getAttributes(SectionMeta::class)[0] ?? null;

        $meta = $attribute?->newInstance() ?? new SectionMeta;

        return new Definition(
            handle: $slug,
            name: $meta->label ?? str($slug)->headline()->toString(),
            description: $meta->description,
            category: $meta->category,
            icon: $meta->icon,
            thumbnail: $meta->thumbnail,
            sourceType: SourceType::ClassBased,
            sourceIdentifier: $sectionClass,
            settings: $settings,
            keywords: $meta->keywords,
        );
    }

    /**
     * @return array<int, string>
     */
    public function slugs(): array
    {
        $codeHandles = [...array_keys($this->sections), ...array_keys($this->singleFileDefinitions)];

        return [...$codeHandles, ...array_values(array_diff(array_keys($this->declarative()), $codeHandles))];
    }

    public function hasHandle(string $handle): bool
    {
        return isset($this->sections[$handle]) || isset($this->singleFileDefinitions[$handle]) || isset($this->declarative()[$handle]);
    }

    /**
     * Reverse lookup: the handle a class-based section is registered under -
     * used to normalise a Page Definition's `Hero::class`-style references
     * into the stable handle actually stored on a PageSection
     * (docs/inlay/v1/page-definitions). A single-file section has no class to look
     * up this way; reference it via SectionHandle::make() instead.
     *
     * @param  class-string<InlaySection>  $class
     */
    public function handleForClass(string $class): string
    {
        $handle = array_search($class, $this->sections, strict: true);

        if ($handle === false) {
            throw new InvalidArgumentException(
                "No handle is registered for section class [{$class}] in config('inlay.sections')."
            );
        }

        return $handle;
    }

    /**
     * The resolved, always-complete metadata for one registered section,
     * for the admin's section picker.
     */
    public function metadata(string $slug): DefinitionMetadata
    {
        return $this->definition($slug)->toMetadata();
    }

    /**
     * @return array<int, DefinitionMetadata>
     */
    public function all(): array
    {
        return array_map($this->metadata(...), $this->slugs());
    }

    /**
     * @return array<string, Definition>
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
