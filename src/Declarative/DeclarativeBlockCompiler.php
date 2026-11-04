<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Declarative;

use WeArePixel\Inlay\Composition\CompositionError;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Composition\SettingsValidator;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Styling\CompiledStylesheets;
use WeArePixel\Inlay\Support\BlockDefinition;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\BlockSourceType;
use WeArePixel\Inlay\Support\LinkValue;

/**
 * Turns an agent-authored block definition (settings plus an element tree)
 * into a BlockDefinition, or rejects it with every problem and its path.
 *
 * The tree is data, never code: only listed tags and attributes, no inline
 * styles or event handlers, links limited to safe schemes, rich text only from
 * rich text settings, and every class must exist in the site's compiled CSS.
 */
final class DeclarativeBlockCompiler
{
    public const SETTING_TYPES = ['text', 'textarea', 'richtext', 'number', 'toggle', 'select', 'image', 'link', 'repeater'];

    public const TAGS = [
        'section', 'div', 'header', 'footer', 'article', 'aside', 'nav',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'strong', 'em', 'small',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'a', 'img', 'figure', 'figcaption',
        'blockquote', 'details', 'summary', 'br', 'hr',
    ];

    public const VOID_TAGS = ['img', 'br', 'hr'];

    public const GLOBAL_ATTRIBUTES = ['id', 'role', 'title', 'aria-label', 'aria-hidden', 'aria-labelledby', 'aria-describedby'];

    public const TAG_ATTRIBUTES = [
        'a' => ['href'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'decoding'],
        'details' => ['open'],
    ];

    private const MAX_DEPTH = 16;

    private const MAX_NODES = 500;

    private const MAX_TEXT = 2000;

    private const TEXT_TYPES = ['text', 'textarea', 'number', 'select'];

    /** @var array<int, CompositionError> */
    private array $errors = [];

    private int $nodeCount = 0;

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly CompiledStylesheets $styles,
        private readonly SettingsValidator $settingsValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     *
     * @throws InvalidCompositionException
     */
    public function compile(array $definition): BlockDefinition
    {
        $this->errors = [];
        $this->nodeCount = 0;

        $handle = $this->validateHandle($definition['handle'] ?? null);
        $settings = $this->buildSettings($definition['settings'] ?? [], 'settings', nested: false);

        if (! $this->styles->available()) {
            $this->errors[] = new CompositionError('template', "No compiled stylesheet was found, so Inlay cannot tell which classes the site's CSS defines. A developer must build the site's assets or set inlay.styling.stylesheets.");
        }

        $template = $definition['template'] ?? null;

        if (! is_array($template) || ! isset($template['tag'])) {
            $this->errors[] = new CompositionError('template', 'Must be a single root element node, e.g. {"tag": "section", "children": [...]}.');
        } else {
            $this->validateNode($template, 'template', $settings, [], 1);
        }

        foreach (['name' => 80, 'description' => 1000, 'category' => 40] as $key => $max) {
            $value = $definition[$key] ?? null;

            if ($key === 'name' && ! is_string($value)) {
                $this->errors[] = new CompositionError('name', 'Required.');
            } elseif ($value !== null && (! is_string($value) || mb_strlen($value) > $max)) {
                $this->errors[] = new CompositionError($key, "Must be a string of at most {$max} characters.");
            }
        }

        if ($this->errors !== []) {
            throw new InvalidCompositionException($this->errors);
        }

        return new BlockDefinition(
            handle: (string) $handle,
            name: (string) $definition['name'],
            description: (string) ($definition['description'] ?? ''),
            category: (string) ($definition['category'] ?? 'Generated'),
            icon: null,
            thumbnail: null,
            sourceType: BlockSourceType::Declarative,
            sourceIdentifier: (string) $handle,
            settings: $settings,
            template: $template,
        );
    }

    private function validateHandle(mixed $handle): ?string
    {
        if (! is_string($handle) || preg_match('/^[a-z][a-z0-9-]{1,59}$/', $handle) !== 1) {
            $this->errors[] = new CompositionError('handle', 'Must be 2-60 lowercase letters, numbers or hyphens, starting with a letter.');

            return null;
        }

        if ($this->blocks->hasHandle($handle) && $this->blocks->definition($handle)->sourceType !== BlockSourceType::Declarative) {
            $this->errors[] = new CompositionError('handle', "\"{$handle}\" is already used by a block a developer registered. Choose another handle.");
        }

        return $handle;
    }

    private function buildSettings(mixed $specs, string $path, bool $nested): Settings
    {
        $settings = Settings::make();

        if (! is_array($specs) || ! array_is_list($specs)) {
            $this->errors[] = new CompositionError($path, 'Must be a list of setting definitions.');

            return $settings;
        }

        $defaults = [];

        foreach ($specs as $index => $spec) {
            $specPath = "{$path}.{$index}";
            $name = is_array($spec) ? ($spec['name'] ?? null) : null;
            $type = is_array($spec) ? ($spec['type'] ?? null) : null;

            if (! is_string($name) || preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/', $name) !== 1) {
                $this->errors[] = new CompositionError("{$specPath}.name", 'Must be a letter followed by up to 39 letters, numbers or underscores.');

                continue;
            }

            if ($settings->field($name) !== null) {
                $this->errors[] = new CompositionError("{$specPath}.name", "Duplicate setting \"{$name}\".");

                continue;
            }

            $allowedTypes = $nested ? array_diff(self::SETTING_TYPES, ['repeater']) : self::SETTING_TYPES;

            if (! in_array($type, $allowedTypes, true)) {
                $this->errors[] = new CompositionError("{$specPath}.type", 'Must be one of: '.implode(', ', $allowedTypes).'.');

                continue;
            }

            $this->addSetting($settings, $name, $type, $spec, $specPath);

            if (array_key_exists('default', $spec)) {
                $defaults[$name] = $spec['default'];
            }
        }

        foreach ($this->settingsValidator->validate($settings, $defaults, $path.'.defaults') as $error) {
            $this->errors[] = $error;
        }

        foreach ($settings->fields() as $setting) {
            $default = $defaults[$setting->name] ?? $this->emptyDefault($setting->type);

            if ($setting->type === FieldType::Link && is_array($default)) {
                $default = new LinkValue((string) ($default['label'] ?? ''), (string) ($default['url'] ?? ''), (bool) ($default['openInNewTab'] ?? false));
            }

            $setting->nestedDefault = $default;
            $setting->hasNestedDefault = true;

            if (! $nested) {
                $setting->default = $default;
                $setting->hasConstructorDefault = true;
            }
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function addSetting(Settings $settings, string $name, string $type, array $spec, string $path): void
    {
        if ($type === 'repeater') {
            $settings->repeater($name, $this->buildSettings($spec['item_settings'] ?? [], "{$path}.item_settings", nested: true));
        } else {
            $settings->{$type}($name);
        }

        $settings->label(is_string($spec['label'] ?? null) ? $spec['label'] : str($name)->headline()->toString());

        if (is_string($spec['help'] ?? null)) {
            $settings->help($spec['help']);
        }

        if (($spec['required'] ?? false) === true) {
            $settings->required();
        }

        if (is_int($spec['max_length'] ?? null) && $spec['max_length'] > 0) {
            $settings->maxLength($spec['max_length']);
        }

        if ($type === 'select') {
            $options = [];

            foreach (is_array($spec['options'] ?? null) ? $spec['options'] : [] as $option) {
                if (is_array($option) && is_string($option['value'] ?? null) && $option['value'] !== '') {
                    $options[$option['value']] = is_string($option['label'] ?? null) ? $option['label'] : $option['value'];
                }
            }

            if ($options === []) {
                $this->errors[] = new CompositionError("{$path}.options", 'A select needs at least one {"value": ..., "label": ...} option.');
            }

            $settings->options($options);
        }
    }

    private function emptyDefault(FieldType $type): mixed
    {
        return match ($type) {
            FieldType::Toggle => false,
            FieldType::Number => 0,
            FieldType::Link => new LinkValue,
            FieldType::Repeater => [],
            default => '',
        };
    }

    /**
     * @param  array<string, Settings>  $scopes  loop variables in scope, keyed by name
     */
    private function validateNode(mixed $node, string $path, Settings $settings, array $scopes, int $depth): void
    {
        if (++$this->nodeCount > self::MAX_NODES) {
            if ($this->nodeCount === self::MAX_NODES + 1) {
                $this->errors[] = new CompositionError($path, 'A block may have at most '.self::MAX_NODES.' nodes.');
            }

            return;
        }

        if ($depth > self::MAX_DEPTH) {
            $this->errors[] = new CompositionError($path, 'Nested too deeply (max '.self::MAX_DEPTH.' levels).');

            return;
        }

        if (! is_array($node)) {
            $this->errors[] = new CompositionError($path, 'Each node must be an object.');

            return;
        }

        if (! isset($node['tag'])) {
            $this->validateTextNode($node, $path, $settings, $scopes);

            return;
        }

        $tag = $node['tag'];

        if (! in_array($tag, self::TAGS, true)) {
            $this->errors[] = new CompositionError("{$path}.tag", 'Tag "'.(is_string($tag) ? $tag : '?').'" is not allowed. Allowed: '.implode(', ', self::TAGS).'.');

            return;
        }

        $known = ['tag', 'class', 'attrs', 'children', 'text', 'html', 'if', 'each', 'link'];

        foreach (array_keys($node) as $key) {
            if (! in_array($key, $known, true)) {
                $this->errors[] = new CompositionError("{$path}.{$key}", 'Unknown node key. Valid keys: '.implode(', ', $known).'.');
            }
        }

        if (isset($node['each'])) {
            $scopes = $this->validateEach($node['each'], "{$path}.each", $settings, $scopes);
        }

        if (isset($node['if'])) {
            $this->validateCondition($node['if'], "{$path}.if", $settings, $scopes);
        }

        if (isset($node['class'])) {
            $this->validateClasses($node['class'], "{$path}.class", $settings, $scopes);
        }

        $this->validateAttributes($tag, $node, $path, $settings, $scopes);

        $contentKeys = array_values(array_intersect(['children', 'text', 'html'], array_keys($node)));

        if (count($contentKeys) > 1) {
            $this->errors[] = new CompositionError($path, 'Use only one of children, text or html.');
        }

        if (in_array($tag, self::VOID_TAGS, true) && $contentKeys !== []) {
            $this->errors[] = new CompositionError($path, "<{$tag}> cannot have content.");
        }

        if (isset($node['link'])) {
            if ($tag !== 'a') {
                $this->errors[] = new CompositionError("{$path}.link", 'Only an "a" node can take a link.');
            } else {
                $this->expectBinding($node['link'], "{$path}.link", $settings, $scopes, [FieldType::Link]);
            }
        }

        if (isset($node['text'])) {
            $this->validateTextValue($node['text'], "{$path}.text", $settings, $scopes);
        }

        if (isset($node['html'])) {
            $this->expectBinding($node['html'], "{$path}.html", $settings, $scopes, [FieldType::Richtext]);
        }

        if (isset($node['children'])) {
            if (! is_array($node['children']) || ! array_is_list($node['children'])) {
                $this->errors[] = new CompositionError("{$path}.children", 'Must be a list of nodes.');
            } else {
                foreach ($node['children'] as $index => $child) {
                    $this->validateNode($child, "{$path}.children.{$index}", $settings, $scopes, $depth + 1);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, Settings>  $scopes
     */
    private function validateTextNode(array $node, string $path, Settings $settings, array $scopes): void
    {
        if (array_keys($node) !== ['text'] && array_keys($node) !== ['text', 'if'] && array_keys($node) !== ['if', 'text']) {
            $this->errors[] = new CompositionError($path, 'A node needs a "tag", or is a text node {"text": ...} (optionally with "if").');

            return;
        }

        if (isset($node['if'])) {
            $this->validateCondition($node['if'], "{$path}.if", $settings, $scopes);
        }

        $this->validateTextValue($node['text'], "{$path}.text", $settings, $scopes);
    }

    /**
     * @param  array<string, Settings>  $scopes
     */
    private function validateTextValue(mixed $value, string $path, Settings $settings, array $scopes): void
    {
        if (is_string($value)) {
            if (mb_strlen($value) > self::MAX_TEXT) {
                $this->errors[] = new CompositionError($path, 'Literal text may be at most '.self::MAX_TEXT.' characters. Use a setting for longer content.');
            }

            return;
        }

        $this->expectBinding($value, $path, $settings, $scopes, $this->textTypes());
    }

    /**
     * @param  array<string, Settings>  $scopes
     * @return array<string, Settings>
     */
    private function validateEach(mixed $each, string $path, Settings $settings, array $scopes): array
    {
        $setting = $this->expectBinding($each, $path, $settings, $scopes, [FieldType::Repeater]);
        $as = is_array($each) ? ($each['as'] ?? 'item') : 'item';

        if (! is_string($as) || preg_match('/^[a-z][a-zA-Z0-9]{0,19}$/', $as) !== 1 || $settings->field($as) !== null) {
            $this->errors[] = new CompositionError("{$path}.as", 'Must be a short lowercase name that is not also a setting name, e.g. "item".');

            return $scopes;
        }

        if ($setting?->itemSchema !== null) {
            $scopes[$as] = $setting->itemSchema;
        }

        return $scopes;
    }

    /**
     * @param  array<string, Settings>  $scopes
     */
    private function validateCondition(mixed $condition, string $path, Settings $settings, array $scopes): void
    {
        $setting = $this->expectBinding($condition, $path, $settings, $scopes, null);

        if ($setting === null || ! is_array($condition)) {
            return;
        }

        foreach (array_keys($condition) as $key) {
            if (! in_array($key, ['setting', 'equals', 'not_equals'], true)) {
                $this->errors[] = new CompositionError("{$path}.{$key}", 'A condition takes "setting" and optionally "equals" or "not_equals".');
            }
        }

        foreach (['equals', 'not_equals'] as $key) {
            if (array_key_exists($key, $condition) && ! is_scalar($condition[$key])) {
                $this->errors[] = new CompositionError("{$path}.{$key}", 'Must be a string, number or boolean.');
            }
        }
    }

    /**
     * @param  array<string, Settings>  $scopes
     */
    private function validateClasses(mixed $classes, string $path, Settings $settings, array $scopes): void
    {
        $parts = is_array($classes) && array_is_list($classes) ? $classes : [$classes];

        foreach ($parts as $index => $part) {
            $partPath = count($parts) > 1 || is_array($classes) ? "{$path}.{$index}" : $path;

            if (is_array($part)) {
                if (! is_string($part['class'] ?? null) || ! isset($part['when'])) {
                    $this->errors[] = new CompositionError($partPath, 'A conditional class is {"class": "...", "when": {"setting": ..., "equals": ...}}.');

                    continue;
                }

                $this->validateCondition($part['when'], "{$partPath}.when", $settings, $scopes);
                $part = $part['class'];
            }

            if (! is_string($part)) {
                $this->errors[] = new CompositionError($partPath, 'Must be a string of class names, or a list of strings and conditional classes.');

                continue;
            }

            foreach (preg_split('/\s+/', trim($part)) ?: [] as $class) {
                if ($class === '' || ! $this->styles->available() || $this->styles->allows($class)) {
                    continue;
                }

                $suggestions = $this->styles->suggest($class);

                $this->errors[] = new CompositionError(
                    $partPath,
                    "\"{$class}\" is not defined in the site's CSS.".($suggestions !== [] ? ' Similar: '.implode(', ', $suggestions).'.' : ' Call inlay-get-styling for the classes you can use.'),
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, Settings>  $scopes
     */
    private function validateAttributes(string $tag, array $node, string $path, Settings $settings, array $scopes): void
    {
        $attributes = $node['attrs'] ?? [];

        if (! is_array($attributes) || ($attributes !== [] && array_is_list($attributes))) {
            $this->errors[] = new CompositionError("{$path}.attrs", 'Must be an object of attribute names to values.');

            return;
        }

        $allowed = [...self::GLOBAL_ATTRIBUTES, ...(self::TAG_ATTRIBUTES[$tag] ?? [])];

        foreach ($attributes as $name => $value) {
            $attributePath = "{$path}.attrs.{$name}";

            if (! in_array($name, $allowed, true)) {
                $this->errors[] = new CompositionError($attributePath, "Attribute \"{$name}\" is not allowed on <{$tag}>. Allowed: ".implode(', ', $allowed).'. Styling goes in "class".');

                continue;
            }

            if ($name === 'src') {
                $this->expectBinding($value, $attributePath, $settings, $scopes, [FieldType::Image]);

                continue;
            }

            if (is_array($value)) {
                $this->expectBinding($value, $attributePath, $settings, $scopes, $this->textTypes());

                continue;
            }

            if (! is_string($value) || mb_strlen($value) > 500) {
                $this->errors[] = new CompositionError($attributePath, 'Must be a string of at most 500 characters, or {"setting": ...}.');

                continue;
            }

            if ($name === 'href' && ! $this->isSafeHref($value)) {
                $this->errors[] = new CompositionError($attributePath, 'Only http(s), mailto, tel, relative and #anchor links are allowed.');
            }

            if ($name === 'id' && preg_match('/^[A-Za-z][\w-]{0,63}$/', $value) !== 1) {
                $this->errors[] = new CompositionError($attributePath, 'Must start with a letter and contain only letters, numbers, hyphens or underscores.');
            }
        }

        if ($tag === 'img' && ! array_key_exists('src', $attributes)) {
            $this->errors[] = new CompositionError("{$path}.attrs.src", 'An image needs "src": {"setting": "<image setting>"}.');
        }

        if ($tag === 'img' && ! array_key_exists('alt', $attributes)) {
            $this->errors[] = new CompositionError("{$path}.attrs.alt", 'An image needs "alt": a text setting, or "" for a purely decorative image.');
        }
    }

    /**
     * @param  array<string, Settings>  $scopes
     * @param  array<int, FieldType>|null  $types  null accepts any type
     */
    private function expectBinding(mixed $binding, string $path, Settings $settings, array $scopes, ?array $types): ?Setting
    {
        $name = is_array($binding) ? ($binding['setting'] ?? null) : null;

        if (! is_string($name)) {
            $this->errors[] = new CompositionError($path, 'Must be {"setting": "<setting name>"}.');

            return null;
        }

        $setting = $this->resolveBinding($name, $settings, $scopes);

        if ($setting === null) {
            $this->errors[] = new CompositionError($path, "No setting \"{$name}\" here. Top-level settings are referenced by name; repeater item fields as \"<as>.<field>\" inside the node that loops over them.");

            return null;
        }

        if ($types !== null && ! in_array($setting->type, $types, true)) {
            $this->errors[] = new CompositionError($path, "\"{$name}\" is a {$setting->type->value} setting; this needs: ".implode(', ', array_map(fn (FieldType $type) => $type->value, $types)).'.');

            return null;
        }

        return $setting;
    }

    /**
     * @param  array<string, Settings>  $scopes
     */
    private function resolveBinding(string $name, Settings $settings, array $scopes): ?Setting
    {
        if (str_contains($name, '.')) {
            [$scope, $field] = explode('.', $name, 2);

            return isset($scopes[$scope]) ? $scopes[$scope]->field($field) : null;
        }

        return $settings->field($name);
    }

    /**
     * @return array<int, FieldType>
     */
    private function textTypes(): array
    {
        return array_map(fn (string $type) => FieldType::from($type), self::TEXT_TYPES);
    }

    public static function isSafeHref(string $href): bool
    {
        $href = trim($href);

        return ! str_contains($href, ':') || preg_match('/^(https?|mailto|tel):/i', $href) === 1;
    }
}
