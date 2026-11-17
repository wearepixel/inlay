<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use InvalidArgumentException;
use ReflectionClass;
use WeArePixel\Inlay\Attributes\BlockMeta;
use WeArePixel\Inlay\Contracts\InlayBlock;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Settings;

/**
 * Every block an editor can place inside a section's blocks area -
 * class-based ones from config('inlay.blocks') and single-file ones from
 * Inlay::singleFileBlocks().
 */
final class BlockRegistry
{
    /** @var array<string, Definition> */
    private array $singleFileDefinitions = [];

    /** @var array<string, Definition> */
    private array $resolved = [];

    /**
     * @param  array<string, class-string<InlayBlock>>  $classes
     */
    public function __construct(private readonly array $classes = []) {}

    public static function fromConfig(): self
    {
        return new self(config('inlay.blocks', []));
    }

    public function registerSingleFile(Definition $definition): void
    {
        if (isset($this->classes[$definition->handle]) || isset($this->singleFileDefinitions[$definition->handle])) {
            throw new InvalidArgumentException("Cannot register single-file block [{$definition->handle}] - a block is already registered for that handle.");
        }

        self::assertNoNestedBlocks($definition->handle, $definition->settings);

        $this->singleFileDefinitions[$definition->handle] = $definition;
    }

    public function hasHandle(string $handle): bool
    {
        return isset($this->classes[$handle]) || isset($this->singleFileDefinitions[$handle]);
    }

    public function definition(string $handle): Definition
    {
        if (isset($this->singleFileDefinitions[$handle])) {
            return $this->singleFileDefinitions[$handle];
        }

        if (! isset($this->classes[$handle])) {
            throw new InvalidArgumentException("No block registered for handle [{$handle}].");
        }

        return $this->resolved[$handle] ??= $this->resolveClass($handle, $this->classes[$handle]);
    }

    /**
     * @return array<int, string>
     */
    public function handles(): array
    {
        return [...array_keys($this->classes), ...array_keys($this->singleFileDefinitions)];
    }

    /**
     * The blocks a blocks field allows, in registration order.
     *
     * @param  array<int, string>  $allowed  empty allows every registered block
     * @return array<int, Definition>
     */
    public function allowedBy(array $allowed): array
    {
        $handles = $allowed === []
            ? $this->handles()
            : array_values(array_filter($allowed, $this->hasHandle(...)));

        return array_map($this->definition(...), $handles);
    }

    /**
     * @param  class-string<InlayBlock>  $class
     */
    private function resolveClass(string $handle, string $class): Definition
    {
        $settings = app(DefinitionResolver::class)->resolve($class);
        self::assertNoNestedBlocks($handle, $settings);

        $meta = ((new ReflectionClass($class))->getAttributes(BlockMeta::class)[0] ?? null)?->newInstance() ?? new BlockMeta;

        return new Definition(
            handle: $handle,
            name: $meta->label ?? str($handle)->headline()->toString(),
            description: $meta->description,
            category: $meta->category,
            icon: $meta->icon,
            thumbnail: $meta->thumbnail,
            sourceType: SourceType::ClassBased,
            sourceIdentifier: $class,
            settings: $settings,
            keywords: $meta->keywords,
        );
    }

    private static function assertNoNestedBlocks(string $handle, Settings $settings): void
    {
        foreach ($settings->fields() as $field) {
            if ($field->type === FieldType::Blocks) {
                throw new InvalidArgumentException("Block [{$handle}] declares a blocks field [{$field->name}]; blocks cannot contain blocks.");
            }
        }
    }
}
