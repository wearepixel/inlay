<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

use InvalidArgumentException;
use ReflectionClass;
use WeArePixel\Inlay\Attributes\PageDefinitionMeta;
use WeArePixel\Inlay\Contracts\InlayBlock;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\BlockHandle;
use WeArePixel\Inlay\Support\BlockRegistry;

/**
 * Resolves a registered PageDefinition class into a ResolvedPageDefinition -
 * every Block reference normalised to its stable registered handle, and
 * validated immediately at registration time rather than the first time a
 * Page happens to use it. The built-in, unrestricted DefaultPageDefinition
 * is always registered, so a developer who never calls
 * Inlay::pageDefinition() still has somewhere for every Page to point.
 */
final class PageDefinitionRegistry
{
    public const DEFAULT_HANDLE = 'default';

    /**
     * @var array<string, ResolvedPageDefinition>
     */
    private array $definitions = [];

    public function __construct(private readonly BlockRegistry $blocks)
    {
        $this->register(DefaultPageDefinition::class);
    }

    /**
     * @param  class-string<PageDefinition>  $definitionClass
     */
    public function register(string $definitionClass): void
    {
        $resolved = $this->resolve($definitionClass);

        if (isset($this->definitions[$resolved->handle])) {
            $existing = $this->definitions[$resolved->handle]->definitionClass;

            throw new InvalidArgumentException(
                "Cannot register Page Definition [{$definitionClass}] under handle [{$resolved->handle}] - ".
                "[{$existing}] is already registered under it. Give one an explicit, distinct #[PageDefinitionMeta(handle: ...)]."
            );
        }

        $this->definitions[$resolved->handle] = $resolved;
    }

    public function has(string $handle): bool
    {
        return isset($this->definitions[$handle]);
    }

    public function definition(string $handle): ?ResolvedPageDefinition
    {
        return $this->definitions[$handle] ?? null;
    }

    /**
     * @return array<int, ResolvedPageDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function default(): ResolvedPageDefinition
    {
        return $this->definitions[self::DEFAULT_HANDLE];
    }

    /**
     * Whichever Page Definition a Page points to, or the built-in default
     * when it points to none (a null `definition` column). Returns null
     * only when the Page's own handle no longer matches anything
     * registered (a definition class renamed or removed after Pages were
     * created against it) - callers show a clear admin warning for that
     * case rather than guessing which rules should apply.
     */
    public function resolveForPage(Page $page): ?ResolvedPageDefinition
    {
        return $this->definition($page->definition ?? self::DEFAULT_HANDLE);
    }

    private function resolve(string $definitionClass): ResolvedPageDefinition
    {
        $instance = new $definitionClass;
        $reflection = new ReflectionClass($definitionClass);

        $attribute = $reflection->getAttributes(PageDefinitionMeta::class)[0] ?? null;
        $meta = $attribute?->newInstance() ?? new PageDefinitionMeta;

        $handle = $meta->handle ?? str($reflection->getShortName())->kebab()->toString();
        $label = $meta->label ?? str($reflection->getShortName())->headline()->toString();

        $locked = $this->normalizeHandles($definitionClass, 'lockedBlocks', $instance->lockedBlocks());
        $required = $this->normalizeHandles($definitionClass, 'requiredBlocks', $instance->requiredBlocks());
        $default = $this->normalizeHandles($definitionClass, 'defaultBlocks', $instance->defaultBlocks(), allowDuplicates: true);

        // A locked Block is always also required
        // (docs/inlay/v1/page-definitions#locked) - rejecting the
        // contradiction here is preferred over silently normalising it in
        // either direction, per the same
        // "fail loudly on a genuine developer mistake" philosophy as
        // SectionRenderer's settings/context collision check.
        $lockedNotRequired = array_diff($locked, $required);

        if ($lockedNotRequired !== []) {
            throw new InvalidArgumentException(
                "Page Definition [{$definitionClass}] locks block(s) [".implode(', ', $lockedNotRequired).'] '.
                'without also requiring them. A locked block must always be required - list it in requiredBlocks() too.'
            );
        }

        $allowedRaw = $instance->allowedBlocks();

        // A required or locked (or default) Block is always also allowed,
        // whether or not the definition separately lists it in
        // allowedBlocks() - so a developer never has to restate the same
        // handle in two places just to satisfy this guarantee.
        $allowed = $allowedRaw === null
            ? null
            : array_values(array_unique([
                ...$this->normalizeHandles($definitionClass, 'allowedBlocks', $allowedRaw),
                ...$required,
                ...$locked,
                ...$default,
            ]));

        return new ResolvedPageDefinition(
            handle: $handle,
            label: $label,
            description: $meta->description,
            icon: $meta->icon,
            allowedBlocks: $allowed,
            requiredBlocks: $required,
            lockedBlocks: $locked,
            defaultBlocks: $default,
            definitionClass: $definitionClass,
        );
    }

    /**
     * Normalises a PageDefinition method's Block references into stable,
     * verified handles - never accepting a bare, untyped string, which
     * BlockHandle exists specifically to rule out (docs/inlay/v1/page-definitions).
     *
     * $references is intentionally typed as `mixed` element-wise, not the
     * `class-string<InlayBlock>|BlockHandle` the public API documents and
     * expects - PHP does not enforce an array's element types at runtime,
     * so a PageDefinition subclass overriding e.g. allowedBlocks() can
     * still hand back anything at all despite the declared return type;
     * the match() below is exactly the runtime check that catches it, and
     * a stricter phpdoc here would tell PHPStan to treat that check as
     * dead code.
     *
     * @param  array<int, mixed>  $references
     * @return array<int, string>
     */
    private function normalizeHandles(string $definitionClass, string $method, array $references, bool $allowDuplicates = false): array
    {
        $handles = [];

        foreach ($references as $reference) {
            $handle = match (true) {
                $reference instanceof BlockHandle => $reference->value,
                is_string($reference) && is_a($reference, InlayBlock::class, true) => $this->blocks->handleForClass($reference),
                default => throw new InvalidArgumentException(
                    "Page Definition [{$definitionClass}]::{$method}() must return block class-strings implementing ".
                    'InlayBlock, or BlockHandle instances - got '.
                    (is_string($reference) ? "string [{$reference}]" : get_debug_type($reference)).'.'
                ),
            };

            if (! $this->blocks->hasHandle($handle)) {
                throw new InvalidArgumentException(
                    "Page Definition [{$definitionClass}]::{$method}() references block [{$handle}], which is not registered."
                );
            }

            $handles[] = $handle;
        }

        return $allowDuplicates ? $handles : array_values(array_unique($handles));
    }
}
