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
use WeArePixel\Inlay\Support\RichTextSanitizer;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Support\SettingsCoercer;

final class UpdatePageSection
{
    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly PageDefinitionRegistry $definitions,
        private readonly SettingsValidator $validator,
        private readonly SettingsCoercer $coercer,
        private readonly SectionRenderer $renderer,
        private readonly RichTextSanitizer $richText,
    ) {}

    /**
     * Merges $patch into the section's stored overrides. A null value removes
     * the override so the field follows the section's default again.
     *
     * @param  array<string, mixed>  $patch
     *
     * @throws InvalidCompositionException
     */
    public function execute(Page $page, int $sectionId, array $patch): PageSection
    {
        return DB::transaction(fn () => $this->apply($page, $sectionId, $patch));
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function apply(Page $page, int $sectionId, array $patch): PageSection
    {
        if ($this->definitions->resolveForPage($page) === null) {
            throw new InvalidCompositionException([new CompositionError('definition', "This page's type \"{$page->definition}\" is no longer registered, so it cannot be edited until a developer resolves it.")]);
        }

        $section = $page->sections()->whereKey($sectionId)->lockForUpdate()->first();

        if ($section === null) {
            throw new InvalidCompositionException([new CompositionError('section_id', "Section {$sectionId} does not exist on this page. Call inlay-get-page for its section ids.")]);
        }

        if (! $this->sections->hasHandle($section->handle)) {
            throw new InvalidCompositionException([new CompositionError('section_id', "Section {$sectionId} uses the unregistered section \"{$section->handle}\" and cannot be edited.")]);
        }

        $sectionType = $this->sections->definition($section->handle);
        $errors = $this->validator->validate($sectionType->settings, $patch, 'settings');

        $stored = $section->settings ?? [];

        foreach ($patch as $name => $value) {
            $setting = $sectionType->settings->field((string) $name);

            if ($setting === null) {
                continue;
            }

            if ($value === null) {
                unset($stored[$name]);

                continue;
            }

            $stored[$name] = $this->richText->sanitizeSetting($setting, $this->coercer->coerce($setting, $value));
        }

        if ($errors === []) {
            $effective = $this->renderer->resolveEffectiveRawSettings($sectionType, $stored);
            $errors = $this->validator->validateRequired($sectionType->settings, $effective, 'settings');
        }

        if ($errors !== []) {
            throw new InvalidCompositionException($errors);
        }

        $section->update(['settings' => $stored]);
        $page->touch();

        return $section;
    }
}
