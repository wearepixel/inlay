<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Composition\CompositionError;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Composition\SettingsValidator;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\ResolvedPageDefinition;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\RichTextSanitizer;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Support\SettingsCoercer;

/**
 * Replaces a page's whole draft composition in one step, all or nothing.
 * Applies the same guardrails the editor enforces one action at a time:
 * only registered and allowed blocks, locked blocks keep their position,
 * required blocks stay present.
 */
final class ReplacePageComposition
{
    public const MAX_SECTIONS = 100;

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly PageDefinitionRegistry $definitions,
        private readonly SettingsValidator $validator,
        private readonly SettingsCoercer $coercer,
        private readonly SectionRenderer $renderer,
        private readonly RichTextSanitizer $richText,
    ) {}

    /**
     * @param  array<int, mixed>  $sections  each ['block' => handle, 'settings' => [...]]
     *
     * @throws InvalidCompositionException
     */
    public function execute(Page $page, array $sections): void
    {
        DB::transaction(function () use ($page, $sections) {
            Page::query()->whereKey($page->getKey())->lockForUpdate()->first();

            $errors = $this->validate($page, $sections);

            if ($errors !== []) {
                throw new InvalidCompositionException($errors);
            }

            $page->sections()->delete();

            foreach (array_values($sections) as $index => $section) {
                $page->sections()->create([
                    'handle' => $section['block'],
                    'order' => $index,
                    'settings' => $this->storable($section['block'], $section['settings'] ?? []),
                ]);
            }

            $page->touch();
        });
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, CompositionError>
     */
    public function validate(Page $page, array $sections): array
    {
        $definition = $this->definitions->resolveForPage($page);

        if ($definition === null) {
            return [new CompositionError('definition', "This page's type \"{$page->definition}\" is no longer registered, so it cannot be edited until a developer resolves it.")];
        }

        if (! array_is_list($sections)) {
            return [new CompositionError('sections', 'Must be a list of sections.')];
        }

        if (count($sections) > self::MAX_SECTIONS) {
            return [new CompositionError('sections', 'A page can have at most '.self::MAX_SECTIONS.' sections.')];
        }

        $errors = [];

        foreach ($sections as $index => $section) {
            array_push($errors, ...$this->validateSection($definition, $section, "sections.{$index}"));
        }

        return [
            ...$errors,
            ...$this->unregisteredSectionErrors($page),
            ...$this->lockedPositionErrors($page, $definition, $sections),
            ...$this->missingRequiredErrors($definition, $sections),
        ];
    }

    /**
     * @return array<int, CompositionError>
     */
    private function validateSection(ResolvedPageDefinition $definition, mixed $section, string $path): array
    {
        if (! is_array($section) || ! is_string($section['block'] ?? null)) {
            return [new CompositionError("{$path}.block", 'Each section needs a "block" handle. Call inlay-list-blocks for the valid handles.')];
        }

        $handle = $section['block'];

        if (! $this->blocks->hasHandle($handle)) {
            return [new CompositionError("{$path}.block", "No block is registered with the handle \"{$handle}\".")];
        }

        $block = $this->blocks->definition($handle);

        if (! $definition->allows($handle)) {
            $allowed = implode(', ', $definition->allowedBlocks ?? []);

            return [new CompositionError("{$path}.block", "{$block->name} is not allowed on {$definition->label}. Allowed blocks: {$allowed}.")];
        }

        $settings = $section['settings'] ?? [];

        if (! is_array($settings) || ($settings !== [] && array_is_list($settings))) {
            return [new CompositionError("{$path}.settings", 'Must be an object keyed by setting name.')];
        }

        $errors = $this->validator->validate($block->settings, $settings, "{$path}.settings");

        if ($errors !== []) {
            return $errors;
        }

        $effective = $this->renderer->resolveEffectiveRawSettings($block, $this->storable($handle, $settings));

        return $this->validator->validateRequired($block->settings, $effective, "{$path}.settings");
    }

    /**
     * Replacing would delete these sections and their content, and they cannot
     * be resubmitted because their block no longer exists.
     *
     * @return array<int, CompositionError>
     */
    private function unregisteredSectionErrors(Page $page): array
    {
        $errors = [];

        foreach ($page->sections()->orderBy('order')->get()->values() as $index => $existing) {
            if (! $this->blocks->hasHandle($existing->handle)) {
                $errors[] = new CompositionError(
                    "sections.{$index}",
                    "The saved section at index {$index} uses \"{$existing->handle}\", a block that is no longer registered. Replacing the page would delete it, so a developer must resolve it first. inlay-update-section still works on the other sections.",
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, CompositionError>
     */
    private function lockedPositionErrors(Page $page, ResolvedPageDefinition $definition, array $sections): array
    {
        if ($definition->lockedBlocks === []) {
            return [];
        }

        $errors = [];

        foreach ($page->sections()->orderBy('order')->get()->values() as $index => $existing) {
            /** @var PageSection $existing */
            if (! $definition->isLocked($existing->handle)) {
                continue;
            }

            if (($sections[$index]['block'] ?? null) === $existing->handle) {
                continue;
            }

            $name = $this->blocks->hasHandle($existing->handle) ? $this->blocks->definition($existing->handle)->name : $existing->handle;

            $errors[] = new CompositionError(
                "sections.{$index}",
                "{$name} is locked at position {$index} by {$definition->label} and cannot be moved or removed. Keep a \"{$existing->handle}\" section at index {$index}.",
            );
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, CompositionError>
     */
    private function missingRequiredErrors(ResolvedPageDefinition $definition, array $sections): array
    {
        $present = array_map(fn (mixed $section) => is_array($section) ? ($section['block'] ?? null) : null, $sections);
        $errors = [];

        foreach ($definition->requiredBlocks as $handle) {
            if (in_array($handle, $present, true)) {
                continue;
            }

            $name = $this->blocks->hasHandle($handle) ? $this->blocks->definition($handle)->name : $handle;

            $errors[] = new CompositionError('sections', "{$name} is required by {$definition->label}. Add a \"{$handle}\" section.");
        }

        return $errors;
    }

    /**
     * Only the keys the caller supplied are stored, so every other field
     * keeps following the block's default. A null value means "use the
     * default" and is dropped rather than stored.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function storable(string $handle, array $settings): array
    {
        $schema = $this->blocks->definition($handle)->settings;
        $stored = [];

        foreach ($settings as $name => $value) {
            $setting = $schema->field((string) $name);

            if ($setting === null || $value === null) {
                continue;
            }

            $stored[$name] = $this->richText->sanitizeSetting($setting, $this->coercer->coerce($setting, $value));
        }

        return $stored;
    }
}
