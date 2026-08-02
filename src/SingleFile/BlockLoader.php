<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\SingleFile;

use InvalidArgumentException;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\BlockDefinition;
use WeArePixel\Inlay\Support\BlockSourceType;

/**
 * Compiles a single-file block's Blade file into the same
 * BlockDefinition a class-based block resolves to. The file is
 * developer-owned application code, trusted exactly like any other Blade
 * view - reading its declaration works by requiring the raw file directly
 * (not through Blade's compiler) so only its top PHP block executes; the
 * Blade-templated markup below it is inert, non-executable text as far as
 * plain PHP is concerned; output is discarded, not evaluated.
 */
final class BlockLoader
{
    public function load(string $path): BlockDefinition
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("No single-file block found at [{$path}].");
        }

        Registration::startCapturing();

        try {
            ob_start();

            try {
                require $path;
            } finally {
                ob_end_clean();
            }
        } finally {
            // Always stop capturing, even if the required file throws - an
            // exception here must never leave global state stuck mid-capture
            // for every single-file block loaded afterward.
            $block = Registration::stopCapturing();
        }

        if (! $block instanceof Block) {
            throw new InvalidArgumentException(
                "[{$path}] does not declare a single-file block - it must call block(Block::make(...)) in a PHP block at the top of the file."
            );
        }

        return new BlockDefinition(
            handle: $block->handle(),
            name: $block->resolvedName(),
            description: $block->resolvedDescription(),
            category: $block->resolvedCategory(),
            icon: $block->resolvedIcon(),
            thumbnail: $block->resolvedThumbnail(),
            sourceType: BlockSourceType::SingleFile,
            sourceIdentifier: $path,
            settings: $this->applyDeclaredDefaults($block->handle(), $block->resolvedSettings()),
            keywords: $block->resolvedKeywords(),
        );
    }

    /**
     * A single-file block has no constructor, so - unlike a top-level
     * setting on a class-based block, which the constructor is
     * authoritative for - the fluent Settings declaration itself must be
     * authoritative for every top-level field's default. ->default() sets
     * Setting::$nestedDefault/$hasNestedDefault (the mechanism normally
     * reserved for a field nested inside a repeater/group item, which has
     * no constructor parameter to be authoritative instead) - here, with
     * no constructor at all, that becomes the top-level default directly.
     */
    private function applyDeclaredDefaults(string $handle, Settings $settings): Settings
    {
        foreach ($settings->fields() as $setting) {
            $this->applyDeclaredDefault($handle, $setting);
        }

        return $settings;
    }

    private function applyDeclaredDefault(string $handle, Setting $setting): void
    {
        if (! $setting->hasNestedDefault) {
            throw new InvalidArgumentException(
                "Single-file block [{$handle}] setting [{$setting->name}] has no default(). ".
                'A single-file block has no constructor to infer one from - every top-level setting must declare its own default via ->default(...).'
            );
        }

        $setting->hasConstructorDefault = true;
        $setting->default = $setting->nestedDefault;

        // declaredEnumClass (set by ->options(EnumClass::class)) is normally
        // only cross-checked against the constructor's own type by
        // BlockDefinitionResolver - with no constructor to check against
        // here, the fluent declaration is the definitive enum type.
        if ($setting->declaredEnumClass !== null) {
            $setting->enumClass = $setting->declaredEnumClass;
        }
    }
}
