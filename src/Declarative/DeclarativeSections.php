<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Declarative;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Composition\CompositionError;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Models\DeclarativeSection;
use WeArePixel\Inlay\Models\PageRevision;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Support\Definition;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Support\SourceType;

/**
 * Stores agent-authored sections and feeds them to the SectionRegistry. Only
 * consulted when config('inlay.agent_sections.enabled') is true.
 */
final class DeclarativeSections
{
    private const CACHE_KEY = 'inlay:declarative-sections';

    public const VERSION_KEY = 'inlay:declarative-sections:version';

    public function __construct(
        private readonly DeclarativeSectionCompiler $compiler,
        private readonly SectionRegistry $registry,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('inlay.agent_sections.enabled', false);
    }

    /**
     * Stored sections were validated when saved, so they are hydrated without
     * re-validation - a class removed from the CSS since then only loses its
     * styling, it does not stop the section rendering.
     *
     * @return array<string, Definition>
     */
    public function definitions(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DeclarativeSection::query()
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
    public function save(array $definition, ?int $userId): Definition
    {
        $compiled = $this->compiler->compile($definition);

        DB::transaction(function () use ($compiled, $definition, $userId) {
            $sectionType = DeclarativeSection::query()->firstOrNew(['handle' => $compiled->handle]);
            $sectionType->definition = $definition;
            $sectionType->created_by ??= $userId;
            $sectionType->updated_by = $userId;
            $sectionType->save();
        });

        $this->forget();

        return $compiled;
    }

    /**
     * @throws InvalidCompositionException
     */
    public function delete(string $handle): void
    {
        $sectionType = DeclarativeSection::query()->where('handle', $handle)->first();

        if ($sectionType === null) {
            throw new InvalidCompositionException([new CompositionError('handle', "No agent-authored section has the handle \"{$handle}\".")]);
        }

        if ($this->registry->hasHandle($handle) && $this->registry->definition($handle)->sourceType !== SourceType::Declarative) {
            $sectionType->delete();
            $this->forget();

            return;
        }

        if ($this->usedByPublishedPage($handle)) {
            throw new InvalidCompositionException([new CompositionError('handle', "\"{$handle}\" is used by a published page, which would lose that section. Publish the page without it first.")]);
        }

        $uses = PageSection::query()->where('handle', $handle)->count();

        if ($uses > 0) {
            throw new InvalidCompositionException([new CompositionError('handle', "\"{$handle}\" is used by {$uses} section(s). Remove it from those pages first.")]);
        }

        $sectionType->delete();
        $this->forget();
    }

    /**
     * Published revisions store a section's handle and settings but not its
     * template, so changing a section they use changes live pages.
     */
    public function usedByPublishedPage(string $handle): bool
    {
        $encoded = json_encode($handle);

        return PageRevision::query()
            ->where(fn ($query) => $query
                ->where('snapshot', 'like', '%"handle":'.$encoded.'%')
                ->orWhere('snapshot', 'like', '%"handle": '.$encoded.'%'))
            ->exists();
    }

    public function exists(string $handle): bool
    {
        return DeclarativeSection::query()->where('handle', $handle)->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function source(string $handle): ?array
    {
        return DeclarativeSection::query()->where('handle', $handle)->value('definition');
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    private function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forever(self::VERSION_KEY, self::version() + 1);
        $this->registry->forgetDeclarative();
    }
}
