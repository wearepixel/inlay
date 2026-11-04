<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Declarative;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Composition\CompositionError;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Models\DeclarativeBlock;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Support\BlockDefinition;
use WeArePixel\Inlay\Support\BlockRegistry;

/**
 * Stores agent-authored blocks and feeds them to the BlockRegistry. Only
 * consulted when config('inlay.agent_blocks.enabled') is true.
 */
final class DeclarativeBlocks
{
    private const CACHE_KEY = 'inlay:declarative-blocks';

    public function __construct(
        private readonly DeclarativeBlockCompiler $compiler,
        private readonly BlockRegistry $registry,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('inlay.agent_blocks.enabled', false);
    }

    /**
     * Stored blocks were validated when saved, so they are hydrated without
     * re-validation - a class removed from the CSS since then only loses its
     * styling, it does not stop the block rendering.
     *
     * @return array<string, BlockDefinition>
     */
    public function definitions(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DeclarativeBlock::query()
            ->orderBy('handle')
            ->pluck('definition', 'handle')
            ->all());

        $definitions = [];

        foreach ($stored as $handle => $definition) {
            $definitions[(string) $handle] = $this->compiler->hydrate((array) $definition);
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $definition
     *
     * @throws InvalidCompositionException
     */
    public function save(array $definition, ?int $userId): BlockDefinition
    {
        $compiled = $this->compiler->compile($definition);

        DB::transaction(function () use ($compiled, $definition, $userId) {
            $block = DeclarativeBlock::query()->firstOrNew(['handle' => $compiled->handle]);
            $block->definition = $definition;
            $block->created_by ??= $userId;
            $block->updated_by = $userId;
            $block->save();
        });

        $this->forget();

        return $compiled;
    }

    /**
     * @throws InvalidCompositionException
     */
    public function delete(string $handle): void
    {
        $block = DeclarativeBlock::query()->where('handle', $handle)->first();

        if ($block === null) {
            throw new InvalidCompositionException([new CompositionError('handle', "No agent-authored block has the handle \"{$handle}\".")]);
        }

        $uses = PageSection::query()->where('handle', $handle)->count();

        if ($uses > 0) {
            throw new InvalidCompositionException([new CompositionError('handle', "\"{$handle}\" is used by {$uses} section(s). Remove it from those pages first.")]);
        }

        $block->delete();
        $this->forget();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function source(string $handle): ?array
    {
        return DeclarativeBlock::query()->where('handle', $handle)->value('definition');
    }

    private function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->registry->forgetDeclarative();
    }
}
