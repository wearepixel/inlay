<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;

/**
 * Sections render rich text unescaped, so HTML written by an agent is cleaned
 * to formatting markup before it is stored: no scripts, event handlers,
 * styles, iframes or non-http(s)/mailto links.
 */
final class RichTextSanitizer
{
    private const ELEMENTS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'blockquote', 'code', 'pre', 'hr', 'span',
    ];

    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(string $html): string
    {
        return $this->sanitizer()->sanitize($html);
    }

    /**
     * Sanitises every rich text value within an already-coerced setting,
     * including those nested in repeater items and groups.
     */
    public function sanitizeSetting(Setting $setting, mixed $value): mixed
    {
        if ($setting->type === FieldType::Richtext && is_string($value)) {
            return $this->sanitize($value);
        }

        if ($setting->type === FieldType::Blocks && is_array($value)) {
            return array_map(fn (mixed $item) => $this->sanitizeBlock($item), $value);
        }

        if ($setting->itemSchema === null || ! is_array($value)) {
            return $value;
        }

        if ($setting->type === FieldType::Repeater) {
            return array_map(fn (mixed $item) => is_array($item) ? $this->sanitizeItem($setting, $item) : $item, $value);
        }

        return $this->sanitizeItem($setting, $value);
    }

    private function sanitizeBlock(mixed $item): mixed
    {
        $blocks = app(BlockRegistry::class);
        $type = is_array($item) ? ($item['type'] ?? null) : null;

        if (! is_string($type) || ! $blocks->hasHandle($type) || ! is_array($item['settings'] ?? null)) {
            return $item;
        }

        $schema = $blocks->definition($type)->settings;

        foreach ($item['settings'] as $name => $value) {
            $field = $schema->field((string) $name);

            if ($field !== null) {
                $item['settings'][$name] = $this->sanitizeSetting($field, $value);
            }
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function sanitizeItem(Setting $setting, array $item): array
    {
        foreach ($item as $name => $nestedValue) {
            $nested = $setting->itemSchema?->field((string) $name);

            if ($nested !== null) {
                $item[$name] = $this->sanitizeSetting($nested, $nestedValue);
            }
        }

        return $item;
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        foreach (self::ELEMENTS as $element) {
            $config = $config->allowElement($element, $element === 'a' ? ['href', 'title', 'target'] : []);
        }

        return $this->sanitizer = new HtmlSanitizer($config);
    }
}
