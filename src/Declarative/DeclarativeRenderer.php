<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Declarative;

use BackedEnum;
use WeArePixel\Inlay\Support\ImageValue;
use WeArePixel\Inlay\Support\LinkValue;
use WeArePixel\Inlay\Support\RichTextSanitizer;

/**
 * Renders a compiled declarative block's element tree. Tags and attributes are
 * re-checked against the allowlists, every value is escaped, rich text is
 * sanitised again and output is capped, so a stored tree that was tampered
 * with after validation still cannot inject markup or exhaust a worker.
 */
final class DeclarativeRenderer
{
    public const MAX_RENDERED_ELEMENTS = 5000;

    private int $rendered = 0;

    public function __construct(private readonly RichTextSanitizer $richText) {}

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $settings  effective, hydrated settings
     */
    public function render(array $template, array $settings): string
    {
        $this->rendered = 0;

        return $this->node($template, $settings, []);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $scope  loop variables
     */
    private function node(mixed $node, array $settings, array $scope): string
    {
        if (! is_array($node)) {
            return '';
        }

        if (isset($node['each'])) {
            $items = $this->value($node['each'], $settings, $scope);
            $as = is_string($node['each']['as'] ?? null) ? $node['each']['as'] : 'item';
            $single = $node;
            unset($single['each']);
            $html = '';

            foreach (array_slice(is_array($items) ? $items : [], 0, 100) as $item) {
                $html .= $this->node($single, $settings, [...$scope, $as => is_array($item) ? $item : []]);
            }

            return $html;
        }

        if (isset($node['if']) && ! $this->condition($node['if'], $settings, $scope)) {
            return '';
        }

        if (! isset($node['tag'])) {
            return e($this->text($node['text'] ?? '', $settings, $scope));
        }

        $tag = (string) $node['tag'];

        if (! in_array($tag, DeclarativeBlockCompiler::TAGS, true) || ++$this->rendered > self::MAX_RENDERED_ELEMENTS) {
            return '';
        }

        $allowedAttributes = [...DeclarativeBlockCompiler::GLOBAL_ATTRIBUTES, ...(DeclarativeBlockCompiler::TAG_ATTRIBUTES[$tag] ?? [])];

        $attributes = [];
        $class = $this->classes($node['class'] ?? null, $settings, $scope);

        if ($class !== '') {
            $attributes['class'] = $class;
        }

        $content = null;

        if (isset($node['link'])) {
            $link = $this->value($node['link'], $settings, $scope);

            if (! $link instanceof LinkValue || $link->url === '' || ! DeclarativeBlockCompiler::isSafeHref($link->url)) {
                return '';
            }

            $attributes['href'] = $link->url;

            if ($link->openInNewTab) {
                $attributes['target'] = '_blank';
                $attributes['rel'] = 'noopener noreferrer';
            }

            $content = e($link->label);
        }

        foreach (is_array($node['attrs'] ?? null) ? $node['attrs'] : [] as $name => $value) {
            if (! in_array($name, $allowedAttributes, true) || isset($attributes[$name])) {
                continue;
            }

            if ($name === 'id' && (! is_string($value) || preg_match('/^[A-Za-z][\w-]{0,63}$/', $value) !== 1)) {
                continue;
            }

            $resolved = $this->attribute((string) $name, $value, $settings, $scope);

            if ($resolved === null) {
                return '';
            }

            $attributes[$name] = $resolved;
        }

        $open = '<'.$tag.$this->attributeString($attributes).'>';

        if (in_array($tag, DeclarativeBlockCompiler::VOID_TAGS, true)) {
            return $open;
        }

        if (isset($node['html'])) {
            $content = $this->richText->sanitize((string) $this->text($node['html'], $settings, $scope));
        } elseif (isset($node['text'])) {
            $content = e($this->text($node['text'], $settings, $scope));
        } elseif (isset($node['children']) && is_array($node['children'])) {
            $content = implode('', array_map(fn (mixed $child) => $this->node($child, $settings, $scope), $node['children']));
        }

        return $open.($content ?? '').'</'.$tag.'>';
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $scope
     * @return string|null null when the element must not render (an image with no source, an unsafe link)
     */
    private function attribute(string $name, mixed $value, array $settings, array $scope): ?string
    {
        if ($name === 'src') {
            $image = $this->value($value, $settings, $scope);
            $url = $image instanceof ImageValue ? $image->url() : '';

            return $url === '' ? null : $url;
        }

        $resolved = is_array($value) ? $this->text($value, $settings, $scope) : (string) $value;

        if ($name === 'href' && ! DeclarativeBlockCompiler::isSafeHref($resolved)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function attributeString(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= ' '.$name.'="'.e($value).'"';
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $scope
     */
    private function classes(mixed $classes, array $settings, array $scope): string
    {
        $parts = is_array($classes) && array_is_list($classes) ? $classes : [$classes];
        $resolved = [];

        foreach ($parts as $part) {
            if (is_array($part)) {
                if (! $this->condition($part['when'] ?? null, $settings, $scope)) {
                    continue;
                }

                $part = $part['class'] ?? '';
            }

            if (is_string($part) && trim($part) !== '') {
                $resolved[] = trim($part);
            }
        }

        return implode(' ', $resolved);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $scope
     */
    private function condition(mixed $condition, array $settings, array $scope): bool
    {
        if (! is_array($condition)) {
            return false;
        }

        $value = $this->plain($this->value($condition, $settings, $scope));

        if (array_key_exists('equals', $condition)) {
            return $this->same($value, $condition['equals']);
        }

        if (array_key_exists('not_equals', $condition)) {
            return ! $this->same($value, $condition['not_equals']);
        }

        return match (true) {
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            default => (bool) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $scope
     */
    private function text(mixed $value, array $settings, array $scope): string
    {
        if (is_string($value)) {
            return $value;
        }

        $resolved = $this->plain($this->value($value, $settings, $scope));

        return is_scalar($resolved) ? (string) $resolved : '';
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $scope
     */
    private function value(mixed $binding, array $settings, array $scope): mixed
    {
        $name = is_array($binding) ? ($binding['setting'] ?? null) : null;

        if (! is_string($name)) {
            return null;
        }

        if (str_contains($name, '.')) {
            [$scopeName, $field] = explode('.', $name, 2);

            return is_array($scope[$scopeName] ?? null) ? ($scope[$scopeName][$field] ?? null) : null;
        }

        return $settings[$name] ?? null;
    }

    private function same(mixed $value, mixed $expected): bool
    {
        if (is_bool($value) || is_bool($expected)) {
            return $value === $expected;
        }

        if (is_numeric($value) && is_numeric($expected)) {
            return (float) $value === (float) $expected;
        }

        return is_scalar($value) && (string) $value === (string) $expected;
    }

    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof ImageValue => $value->path,
            $value instanceof LinkValue => $value->url,
            default => $value,
        };
    }
}
