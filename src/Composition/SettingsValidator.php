<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Composition;

use BackedEnum;
use Illuminate\Support\Facades\Storage;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\SettingsCoercer;

/**
 * Strict counterpart to SettingsCoercer for settings an agent supplies:
 * where the coercer silently repairs a bad value, this reports it with a
 * dotted path so the caller can correct it. A null value is valid for any
 * field and means "use the block's default".
 */
final class SettingsValidator
{
    private const LINK_KEYS = ['label', 'url', 'openInNewTab'];

    public const MAX_REPEATER_ITEMS = 100;

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<int, CompositionError>
     */
    public function validate(Settings $schema, array $values, string $path): array
    {
        $errors = [];

        foreach ($values as $name => $value) {
            $setting = $schema->field((string) $name);
            $fieldPath = "{$path}.{$name}";

            if ($setting === null) {
                $errors[] = new CompositionError($fieldPath, 'Unknown setting. Valid settings: '.$this->fieldNames($schema).'.');

                continue;
            }

            if ($value === null) {
                continue;
            }

            array_push($errors, ...$this->validateValue($setting, $value, $fieldPath));
        }

        return $errors;
    }

    /**
     * Required fields are checked against the effective values (stored and
     * supplied settings merged over the block defaults), so a required field
     * whose default is already filled in never needs repeating.
     *
     * @param  array<string, mixed>  $effective
     * @return array<int, CompositionError>
     */
    public function validateRequired(Settings $schema, array $effective, string $path): array
    {
        $errors = [];

        foreach ($schema->fields() as $setting) {
            if ($setting->required
                && $setting->isVisible($effective)
                && $this->isBlank($effective[$setting->name] ?? null)) {
                $errors[] = new CompositionError("{$path}.{$setting->name}", 'Required.');
            }
        }

        return $errors;
    }

    /**
     * @return array<int, CompositionError>
     */
    private function validateValue(Setting $setting, mixed $value, string $path): array
    {
        return match ($setting->type) {
            FieldType::Text, FieldType::Textarea, FieldType::Richtext => $this->validateText($setting, $value, $path),
            FieldType::Number => (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) && is_finite((float) $value)
                ? []
                : [new CompositionError($path, 'Must be a number.')],
            FieldType::Toggle => is_bool($value) ? [] : [new CompositionError($path, 'Must be true or false.')],
            FieldType::Select => $this->validateSelect($setting, $value, $path),
            FieldType::Image => $this->validateImage($value, $path),
            FieldType::Link => $this->validateLink($value, $path),
            FieldType::Repeater => $this->validateRepeater($setting, $value, $path),
            FieldType::Group => $this->validateGroup($setting, $value, $path),
        };
    }

    /**
     * @return array<int, CompositionError>
     */
    private function validateText(Setting $setting, mixed $value, string $path): array
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return [new CompositionError($path, 'Must be a string.')];
        }

        $length = mb_strlen((string) $value);

        if ($setting->maxLength !== null && $length > $setting->maxLength) {
            return [new CompositionError($path, "Must be at most {$setting->maxLength} characters (got {$length}).")];
        }

        if ($setting->required && $this->isBlank($value)) {
            return [new CompositionError($path, 'Required.')];
        }

        return [];
    }

    /**
     * @return array<int, CompositionError>
     */
    private function validateSelect(Setting $setting, mixed $value, string $path): array
    {
        $candidate = $value instanceof BackedEnum ? $value->value : $value;
        $allowed = array_map('strval', array_keys($setting->options));

        if ((is_string($candidate) || is_int($candidate)) && in_array((string) $candidate, $allowed, true)) {
            return [];
        }

        return [new CompositionError($path, 'Must be one of: '.implode(', ', $allowed).'.')];
    }

    /**
     * Only a relative path already on the Inlay disk is accepted - upload a
     * new image first. Rejecting traversal matters because the path is
     * later turned into a public URL by the disk.
     *
     * @return array<int, CompositionError>
     */
    private function validateImage(mixed $value, string $path): array
    {
        if (! is_string($value)) {
            return [new CompositionError($path, 'Must be an image path string.')];
        }

        if ($value === '') {
            return [];
        }

        if (! str_starts_with($value, 'inlay/') || str_contains($value, '..') || str_contains($value, ':')) {
            return [new CompositionError($path, 'Must be a relative path on the Inlay disk, as returned by inlay-upload-image.')];
        }

        if (! Storage::disk(config('inlay.disk', 'public'))->exists($value)) {
            return [new CompositionError($path, "No image exists at \"{$value}\". Upload it with inlay-upload-image and use the path it returns.")];
        }

        return [];
    }

    /**
     * @return array<int, CompositionError>
     */
    private function validateLink(mixed $value, string $path): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            return [new CompositionError($path, 'Must be an object with label, url and openInNewTab.')];
        }

        $errors = [];

        foreach ($value as $key => $part) {
            if (! in_array($key, self::LINK_KEYS, true)) {
                $errors[] = new CompositionError("{$path}.{$key}", 'Unknown link key. Valid keys: '.implode(', ', self::LINK_KEYS).'.');
            }
        }

        foreach (['label', 'url'] as $key) {
            if (array_key_exists($key, $value) && $value[$key] !== null && ! is_string($value[$key])) {
                $errors[] = new CompositionError("{$path}.{$key}", 'Must be a string.');
            }
        }

        if (is_string($value['url'] ?? null) && ! SettingsCoercer::isAllowedUrlScheme($value['url'])) {
            $errors[] = new CompositionError("{$path}.url", 'Only http(s), mailto and relative URLs are allowed.');
        }

        if (array_key_exists('openInNewTab', $value) && $value['openInNewTab'] !== null && ! is_bool($value['openInNewTab'])) {
            $errors[] = new CompositionError("{$path}.openInNewTab", 'Must be true or false.');
        }

        return $errors;
    }

    /**
     * @return array<int, CompositionError>
     */
    private function validateRepeater(Setting $setting, mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [new CompositionError($path, 'Must be a list of items.')];
        }

        if (count($value) > self::MAX_REPEATER_ITEMS) {
            return [new CompositionError($path, 'Must have at most '.self::MAX_REPEATER_ITEMS.' items.')];
        }

        $errors = [];

        foreach ($value as $index => $item) {
            array_push($errors, ...$this->validateNestedItem($setting, $item, "{$path}.{$index}"));
        }

        return $errors;
    }

    /**
     * @return array<int, CompositionError>
     */
    private function validateGroup(Setting $setting, mixed $value, string $path): array
    {
        return $this->validateNestedItem($setting, $value, $path);
    }

    /**
     * @return array<int, CompositionError>
     */
    private function validateNestedItem(Setting $setting, mixed $item, string $path): array
    {
        $schema = $setting->itemSchema ?? Settings::make();

        if (! is_array($item) || ($item !== [] && array_is_list($item))) {
            return [new CompositionError($path, 'Must be an object with: '.$this->fieldNames($schema).'.')];
        }

        $effective = array_filter($item, fn (mixed $value) => $value !== null);

        foreach ($schema->fields() as $nested) {
            if (! array_key_exists($nested->name, $effective) && $nested->hasNestedDefault) {
                $effective[$nested->name] = $nested->nestedDefault;
            }
        }

        $errors = $this->validate($schema, $item, $path);
        $reported = array_map(fn (CompositionError $error) => $error->path, $errors);

        foreach ($this->validateRequired($schema, $effective, $path) as $missing) {
            if (! in_array($missing->path, $reported, true)) {
                $errors[] = $missing;
            }
        }

        return $errors;
    }

    private function isBlank(mixed $value): bool
    {
        return match (true) {
            $value === null => true,
            is_string($value) => trim($value) === '',
            is_array($value) => $value === [] || (isset($value['url']) && array_key_exists('label', $value) && trim((string) $value['url']) === ''),
            default => false,
        };
    }

    private function fieldNames(Settings $schema): string
    {
        return implode(', ', array_map(fn (Setting $setting) => $setting->name, $schema->fields()));
    }
}
