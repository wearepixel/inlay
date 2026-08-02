<?php

declare(strict_types=1);

namespace WearePixel\Inlay\SingleFile;

use InvalidArgumentException;
use WearePixel\Inlay\Schema\Setting;
use WearePixel\Inlay\Schema\Settings;
use WearePixel\Inlay\Support\ComponentDefinition;
use WearePixel\Inlay\Support\ComponentSourceType;

/**
 * Compiles a single-file component's Blade file into the same
 * ComponentDefinition a class-based component resolves to. The file is
 * developer-owned application code, trusted exactly like any other Blade
 * view - reading its declaration works by requiring the raw file directly
 * (not through Blade's compiler) so only its top PHP block executes; the
 * Blade-templated markup below it is inert, non-executable text as far as
 * plain PHP is concerned; output is discarded, not evaluated.
 */
final class ComponentLoader
{
    public function load(string $path): ComponentDefinition
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("No single-file component found at [{$path}].");
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
            // for every single-file component loaded afterward.
            $component = Registration::stopCapturing();
        }

        if (! $component instanceof Component) {
            throw new InvalidArgumentException(
                "[{$path}] does not declare a single-file component - it must call component(Component::make(...)) in a PHP block at the top of the file."
            );
        }

        return new ComponentDefinition(
            handle: $component->handle(),
            name: $component->resolvedName(),
            description: $component->resolvedDescription(),
            category: $component->resolvedCategory(),
            icon: $component->resolvedIcon(),
            thumbnail: $component->resolvedThumbnail(),
            sourceType: ComponentSourceType::SingleFile,
            sourceIdentifier: $path,
            settings: $this->applyDeclaredDefaults($component->handle(), $component->resolvedSettings()),
        );
    }

    /**
     * A single-file component has no constructor, so - unlike a top-level
     * setting on a class-based component, which the constructor is
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
                "Single-file component [{$handle}] setting [{$setting->name}] has no default(). ".
                'A single-file component has no constructor to infer one from - every top-level setting must declare its own default via ->default(...).'
            );
        }

        $setting->hasConstructorDefault = true;
        $setting->default = $setting->nestedDefault;

        // declaredEnumClass (set by ->options(EnumClass::class)) is normally
        // only cross-checked against the constructor's own type by
        // ComponentDefinitionResolver - with no constructor to check against
        // here, the fluent declaration is the definitive enum type.
        if ($setting->declaredEnumClass !== null) {
            $setting->enumClass = $setting->declaredEnumClass;
        }
    }
}
