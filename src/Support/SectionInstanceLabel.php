<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use Illuminate\Support\Str;
use WeArePixel\Inlay\Schema\FieldType;

/**
 * Turns a page section's raw stored settings into a short, human-readable
 * instance label for the section tree and settings header - "hero" and
 * "hero" for two different sections tells an editor nothing; "Hero -
 * 'Welcome'" and "Hero - 'Our Story'" does. A safe, deterministic
 * heuristic: only ever reads a short plain-text field's value, never
 * textarea/richtext/image/select/repeater/group, so this can never surface
 * long-form or structured content in a one-line list label. A developer can
 * make the choice explicit with Settings::instanceLabel('fieldName')
 * instead of relying on the heuristic below.
 */
final class SectionInstanceLabel
{
    /**
     * Checked in this order before falling back to the first declared Text
     * field - a section author's own field names win the moment one of
     * these matches, so this only ever acts as a fallback guess.
     */
    private const CANDIDATE_NAMES = ['heading', 'title', 'name', 'label'];

    private const MAX_LENGTH = 40;

    /**
     * The resolved text to show alongside the section's display name, or
     * null when nothing suitable was found - the caller falls back to the
     * display name alone in that case.
     *
     * @param  array<string, mixed>  $storedSettings
     */
    public static function detail(Definition $definition, array $storedSettings): ?string
    {
        $schema = $definition->settings;
        $override = $schema->instanceLabelField();

        if ($override !== null) {
            $field = $schema->field($override);

            if ($field && $field->type === FieldType::Text) {
                $value = self::textValue($definition, $storedSettings, $field->name);

                if ($value !== null) {
                    return $value;
                }
            }
        }

        foreach (self::CANDIDATE_NAMES as $name) {
            $field = $schema->field($name);

            if (! $field || $field->type !== FieldType::Text) {
                continue;
            }

            $value = self::textValue($definition, $storedSettings, $name);

            if ($value !== null) {
                return $value;
            }
        }

        foreach ($schema->fields() as $field) {
            if ($field->type !== FieldType::Text) {
                continue;
            }

            $value = self::textValue($definition, $storedSettings, $field->name);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $storedSettings
     */
    private static function textValue(Definition $definition, array $storedSettings, string $fieldName): ?string
    {
        $value = array_key_exists($fieldName, $storedSettings)
            ? $storedSettings[$fieldName]
            : ($definition->defaults()[$fieldName] ?? null);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : Str::limit($trimmed, self::MAX_LENGTH);
    }
}
