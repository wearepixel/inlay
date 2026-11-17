<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use BackedEnum;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;

/**
 * Forces a value into the shape its FieldType stores, repairing rather than
 * rejecting. Both the admin (whose Livewire properties a client can set to
 * anything) and the MCP tools pass every value through this before storage,
 * so a section's constructor never receives a type it can't accept.
 */
final class SettingsCoercer
{
    public function coerce(Setting $setting, mixed $value): mixed
    {
        $itemSchema = $setting->itemSchema;

        return match (true) {
            $setting->type === FieldType::Repeater && $itemSchema instanceof Settings => is_array($value)
                ? array_values(array_map(
                    fn (mixed $item): array => $this->coerceNested($itemSchema, is_array($item) ? $item : []),
                    $value,
                ))
                : [],
            $setting->type === FieldType::Group && $itemSchema instanceof Settings => $this->coerceNested($itemSchema, is_array($value) ? $value : []),
            $setting->type === FieldType::Link => $this->coerceLinkValue($value),
            $setting->type === FieldType::Toggle => is_scalar($value) ? (bool) $value : false,
            $setting->type === FieldType::Number => $this->coerceNumber($value, $setting),
            $setting->type === FieldType::Select && $setting->enumClass !== null => $this->coerceEnumValue($setting, $value),
            default => $this->coerceScalarValue($value),
        };
    }

    /**
     * Consuming apps render a Link field's `url` straight into an `href`.
     * Blade escaping does not neutralise a `javascript:` scheme, so only
     * http(s), mailto and scheme-less (relative) URLs are allowed.
     */
    public static function isAllowedUrlScheme(string $url): bool
    {
        if (! str_contains($url, ':')) {
            return true;
        }

        return (bool) preg_match('/^(https?|mailto):/i', $url);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function coerceNested(Settings $schema, array $values): array
    {
        $result = [];

        foreach ($schema->fields() as $nested) {
            if (($values[$nested->name] ?? null) === null) {
                continue;
            }

            $result[$nested->name] = $this->coerce($nested, $values[$nested->name]);
        }

        return $result;
    }

    /**
     * @return array{label: string, url: string, openInNewTab: bool}
     */
    private function coerceLinkValue(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        $url = is_string($value['url'] ?? null) ? $value['url'] : '';

        return [
            'label' => is_string($value['label'] ?? null) ? $value['label'] : '',
            'url' => self::isAllowedUrlScheme($url) ? $url : '',
            'openInNewTab' => is_scalar($value['openInNewTab'] ?? null) ? (bool) $value['openInNewTab'] : false,
        ];
    }

    /**
     * A value that no longer matches a real case (an enum case was removed)
     * falls back to the default, so stored state never holds a value the
     * renderer would reject.
     */
    private function coerceEnumValue(Setting $setting, mixed $value): int|string
    {
        $enumClass = $setting->enumClass;

        $candidate = $value instanceof BackedEnum ? $value->value : $value;

        if (is_string($candidate) && (string) (new \ReflectionEnum($enumClass))->getBackingType() === 'int') {
            $candidate = ctype_digit($candidate) ? (int) $candidate : null;
        }

        if ((is_string($candidate) || is_int($candidate)) && $enumClass::tryFrom($candidate) !== null) {
            return $candidate;
        }

        $default = $setting->default;

        return match (true) {
            $default instanceof BackedEnum => $default->value,
            is_string($default) => $default,
            default => '',
        };
    }

    private function coerceNumber(mixed $value, Setting $setting): int|float
    {
        if (is_numeric($value)) {
            return $value + 0;
        }

        return is_numeric($setting->default) ? $setting->default + 0 : 0;
    }

    private function coerceScalarValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
