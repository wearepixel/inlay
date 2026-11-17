<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\SingleFile;

use InvalidArgumentException;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\Definition;
use WeArePixel\Inlay\Support\SourceType;

/**
 * Compiles a single-file section's Blade file into the same
 * Definition a class-based section resolves to. The file is
 * developer-owned application code, trusted exactly like any other Blade
 * view - reading its declaration works by requiring the raw file directly
 * (not through Blade's compiler) so only its top PHP block executes; the
 * Blade-templated markup below it is inert, non-executable text as far as
 * plain PHP is concerned; output is discarded, not evaluated.
 */
final class SingleFileLoader
{
    /**
     * @param  class-string<Definable>  $kind
     */
    public function load(string $path, string $kind = Section::class): Definition
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("No single-file section found at [{$path}].");
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
            // for every single-file section loaded afterward.
            $sectionType = Registration::stopCapturing();
        }

        if (! $sectionType instanceof $kind) {
            throw new InvalidArgumentException($kind === Block::class
                ? "[{$path}] does not declare a single-file block - it must call block(Block::make(...)) in a PHP block at the top of the file."
                : "[{$path}] does not declare a single-file section - it must call section(Section::make(...)) in a PHP block at the top of the file.");
        }

        return new Definition(
            handle: $sectionType->handle(),
            name: $sectionType->resolvedName(),
            description: $sectionType->resolvedDescription(),
            category: $sectionType->resolvedCategory(),
            icon: $sectionType->resolvedIcon(),
            thumbnail: $sectionType->resolvedThumbnail(),
            sourceType: SourceType::SingleFile,
            sourceIdentifier: $path,
            settings: $this->applyDeclaredDefaults($sectionType->handle(), $sectionType->resolvedSettings()),
            keywords: $sectionType->resolvedKeywords(),
        );
    }

    /**
     * A single-file section has no constructor, so - unlike a top-level
     * setting on a class-based section, which the constructor is
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
                "Single-file section [{$handle}] setting [{$setting->name}] has no default(). ".
                'A single-file section has no constructor to infer one from - every top-level setting must declare its own default via ->default(...).'
            );
        }

        $setting->hasConstructorDefault = true;
        $setting->default = $setting->nestedDefault;

        // declaredEnumClass (set by ->options(EnumClass::class)) is normally
        // only cross-checked against the constructor's own type by
        // DefinitionResolver - with no constructor to check against
        // here, the fluent declaration is the definitive enum type.
        if ($setting->declaredEnumClass !== null) {
            $setting->enumClass = $setting->declaredEnumClass;
        }
    }
}
