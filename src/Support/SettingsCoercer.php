<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use BackedEnum;
use Illuminate\Support\Str;
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
            $setting->type === FieldType::Repeater && $itemSchema instanceof Settings => $this->coerceRepeater($itemSchema, $value),
            $setting->type === FieldType::Group && $itemSchema instanceof Settings => $this->coerceNested($itemSchema, is_array($value) ? $value : []),
            $setting->type === FieldType::Blocks => $this->coerceBlocks($setting, $value),
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
     * Each item becomes {id, type, settings}. An item whose type is not a
     * registered block the field allows is dropped, an unusable id is
     * replaced, and its settings are coerced against the block's own schema
     * (unknown keys dropped). Anything past max() is cut.
     *
     * @return array<int, array{id: string, type: string, settings: array<string, mixed>}>
     */
    private function coerceBlocks(Setting $setting, mixed $value): array
    {
        $registry = app(BlockRegistry::class);
        $items = [];
        $usedIds = [];

        foreach (is_array($value) ? $value : [] as $item) {
            if (! is_array($item) || ! self::isAllowedBlock($setting, $item['type'] ?? null)) {
                continue;
            }

            $id = self::usableBlockId($item['id'] ?? null, $usedIds);
            $usedIds[] = $id;
            $settings = is_array($item['settings'] ?? null) ? $item['settings'] : [];

            $items[] = [
                'id' => $id,
                'type' => $item['type'],
                'settings' => $this->coerceNested($registry->definition($item['type'])->settings, $settings),
            ];
        }

        $limit = min($setting->maxBlocks ?? Settings::MAX_ITEMS, Settings::MAX_ITEMS);

        return array_slice($items, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function coerceRepeater(Settings $itemSchema, mixed $value): array
    {
        $items = [];

        foreach (is_array($value) ? $value : [] as $item) {
            $items[] = $this->coerceNested($itemSchema, is_array($item) ? $item : []);
        }

        return array_slice($items, 0, Settings::MAX_ITEMS);
    }

    /**
     * Whether $type is a registered block that this blocks field allows.
     */
    public static function isAllowedBlock(Setting $setting, mixed $type): bool
    {
        if (! is_string($type) || ! app(BlockRegistry::class)->hasHandle($type)) {
            return false;
        }

        return $setting->allowedBlocks === [] || in_array($type, $setting->allowedBlocks, true);
    }

    /**
     * Keeps a well-formed id that is not already taken, otherwise issues a new one.
     *
     * @param  array<int, string>  $usedIds
     */
    private static function usableBlockId(mixed $id, array $usedIds): string
    {
        if (is_string($id) && self::isValidBlockId($id) && ! in_array($id, $usedIds, true)) {
            return $id;
        }

        return self::newBlockId();
    }

    public static function isValidBlockId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) === 1;
    }

    public static function newBlockId(): string
    {
        return strtolower((string) Str::ulid());
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
