<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Composition;

use BackedEnum;
use WeArePixel\Inlay\Pages\ResolvedPageDefinition;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\BlockDefinition;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\BlockSourceType;
use WeArePixel\Inlay\Support\ImageValue;
use WeArePixel\Inlay\Support\LinkValue;

/**
 * Describes registered blocks and their settings in a form an agent can
 * compose from: every field's type, the exact value shape it accepts,
 * its constraints and its default.
 */
final class BlockCatalogue
{
    private const VALUE_FORMATS = [
        'text' => 'string',
        'textarea' => 'string (plain text, line breaks allowed)',
        'richtext' => 'string of HTML',
        'number' => 'number',
        'toggle' => 'true or false',
        'select' => 'one of the option values',
        'image' => 'image path returned by inlay-upload-image, or "" for none',
        'link' => 'object {"label": string, "url": string, "openInNewTab": boolean}; url must be relative, http(s) or mailto',
        'repeater' => 'list of objects, each shaped by item_settings',
        'group' => 'object shaped by item_settings',
    ];

    public function __construct(private readonly BlockRegistry $blocks) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function describe(?ResolvedPageDefinition $only = null): array
    {
        $described = [];

        foreach ($this->blocks->slugs() as $handle) {
            if ($only !== null && ! $only->allows($handle)) {
                continue;
            }

            $described[] = $this->describeBlock($this->blocks->definition($handle), $only);
        }

        return $described;
    }

    /**
     * @return array<string, mixed>
     */
    public function describeBlock(BlockDefinition $block, ?ResolvedPageDefinition $definition = null): array
    {
        return array_filter([
            'handle' => $block->handle,
            'name' => $block->name,
            'category' => $block->category,
            'source' => $block->sourceType === BlockSourceType::Declarative ? 'agent' : 'developer',
            'description' => $block->description,
            'keywords' => $block->keywords,
            'thumbnail' => $this->thumbnailUrl($block->thumbnail),
            'required_on_page' => $definition?->isRequired($block->handle) ?: null,
            'locked_on_page' => $definition?->isLocked($block->handle) ?: null,
            'settings' => $this->describeSettings($block->settings),
        ], fn (mixed $value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function describeSettings(Settings $settings, bool $nested = false): array
    {
        return array_map(fn (Setting $setting) => $this->describeSetting($setting, $nested), $settings->fields());
    }

    /**
     * @return array<string, mixed>
     */
    private function describeSetting(Setting $setting, bool $nested): array
    {
        $default = $nested
            ? ($setting->hasNestedDefault ? $setting->nestedDefault : null)
            : $setting->default;

        return array_filter([
            'name' => $setting->name,
            'type' => $setting->type->value,
            'label' => $setting->label,
            'help' => $setting->help,
            'value_format' => self::VALUE_FORMATS[$setting->type->value],
            'required' => $setting->required ?: null,
            'max_length' => $setting->maxLength,
            'options' => $setting->type === FieldType::Select ? $this->options($setting) : null,
            'default' => $this->plain($default),
            'visible_when' => $setting->visibleWhen === null ? null : [
                'setting' => $setting->visibleWhen[0],
                $setting->negateVisibility ? 'not_equals' : 'equals' => $setting->visibleWhen[1],
            ],
            'item_settings' => $setting->itemSchema !== null ? $this->describeSettings($setting->itemSchema, nested: true) : null,
        ], fn (mixed $value) => $value !== null);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function options(Setting $setting): array
    {
        $options = [];

        foreach ($setting->options as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $options;
    }

    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof ImageValue => $value->path,
            $value instanceof LinkValue => ['label' => $value->label, 'url' => $value->url, 'openInNewTab' => $value->openInNewTab],
            is_array($value) => array_map(fn (mixed $item) => $this->plain($item), $value),
            default => $value,
        };
    }

    private function thumbnailUrl(?string $thumbnail): ?string
    {
        return $thumbnail === null || $thumbnail === '' ? null : url($thumbnail);
    }
}
