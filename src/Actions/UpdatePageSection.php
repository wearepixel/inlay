<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Composition\CompositionError;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Composition\SettingsValidator;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\RichTextSanitizer;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Support\SettingsCoercer;

final class UpdatePageSection
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly SettingsValidator $validator,
        private readonly SettingsCoercer $coercer,
        private readonly SectionRenderer $renderer,
        private readonly RichTextSanitizer $richText,
    ) {}

    /**
     * Merges $patch into the section's stored overrides. A null value removes
     * the override so the field follows the block's default again.
     *
     * @param  array<string, mixed>  $patch
     *
     * @throws InvalidCompositionException
     */
    public function execute(Page $page, int $sectionId, array $patch): PageSection
    {
        $section = $page->sections()->whereKey($sectionId)->first();

        if ($section === null) {
            throw new InvalidCompositionException([new CompositionError('section_id', "Section {$sectionId} does not exist on this page. Call inlay-get-page for its section ids.")]);
        }

        if (! $this->blocks->hasHandle($section->handle)) {
            throw new InvalidCompositionException([new CompositionError('section_id', "Section {$sectionId} uses the unregistered block \"{$section->handle}\" and cannot be edited.")]);
        }

        $block = $this->blocks->definition($section->handle);
        $errors = $this->validator->validate($block->settings, $patch, 'settings');

        $stored = $section->settings ?? [];

        foreach ($patch as $name => $value) {
            $setting = $block->settings->field((string) $name);

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
            $effective = $this->renderer->resolveEffectiveRawSettings($block, $stored);
            $errors = $this->validator->validateRequired($block->settings, $effective, 'settings');
        }

        if ($errors !== []) {
            throw new InvalidCompositionException($errors);
        }

        DB::transaction(function () use ($page, $section, $stored) {
            $section->update(['settings' => $stored]);
            $page->touch();
        });

        return $section;
    }
}
