<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use BackedEnum;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Throwable;
use WeArePixel\Inlay\Declarative\DeclarativeRenderer;
use WeArePixel\Inlay\Preview\PreviewTag;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;

/**
 * Renders each registered section through Laravel's real view engine -
 * a class-based section through a genuine, compiled Blade component tag
 * (<x-dynamic-component> resolved via a registered alias, per the mechanism
 * proven in tests/Feature/RendererMechanismSpikeTest.php and documented in
 * docs/v1-implementation-specification.md §5); a single-file section
 * through View::file() against its own Blade file directly. Both paths are
 * real Blade compilation, never manual `new $sectionClass(...)`
 * construction - which is what lets either kind's own view freely nest a
 * genuine, hydrating Livewire component with zero awareness on the
 * package's part.
 */
final class SectionRenderer
{
    /**
     * @param  bool  $tagForPreview  mark each block so the editor preview can outline and select it
     */
    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly bool $tagForPreview = false,
    ) {}

    /**
     * A public entry point a host application can call directly with its
     * own hand-built iterable, so a missing key is a real possibility here,
     * not just an internal-consistency guarantee - handle/settings are
     * defensively treated as optional rather than assumed present.
     *
     * `$context` is host-supplied, request-specific data with no schema
     * Inlay understands (the collection currently being viewed, the
     * authenticated customer) - a section whose Blade view declares a
     * matching property picks it up the same way it already picks up an
     * ordinary container-resolvable dependency (docs/v1-implementation-
     * specification.md §9). Inlay itself never inspects or validates its
     * values, never persists it, and never includes it in a revision
     * snapshot - PublishPage resolves and freezes $settings alone.
     *
     * $settings and $context are kept as two explicit, never-merged-blind
     * concepts rather than one combined array: if a section declares a
     * setting with the same name as a $context key, that's a genuine
     * developer naming conflict with no correct silent resolution (does
     * the persisted setting win, or the live context - a wrong guess
     * either way is a real bug that would only surface once someone
     * actually supplies that context), so it fails loudly here instead -
     * see assertNoSettingsContextCollision(). This check runs outside the
     * per-section try/catch below on purpose: it is a configuration error,
     * not a malformed-content problem the renderer should quietly work
     * around and log.
     *
     * @param  iterable<array{handle?: string, settings?: array<string, mixed>}>  $sections
     * @param  array<string, mixed>|null  $context
     */
    public function render(iterable $sections, ?array $context = null): string
    {
        $context ??= [];
        $html = '';

        foreach ($sections as $section) {
            $definition = null;
            $settings = null;

            try {
                $definition = $this->registry->definition($section['handle'] ?? '');
                $settings = $this->resolveEffectiveSettings($definition, $section['settings'] ?? []);
            } catch (Throwable $exception) {
                // A malformed or unresolvable section must not take down
                // every other section on the page.
                Log::warning('inlay: skipped a section that failed to render', [
                    'handle' => $section['handle'] ?? null,
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            $this->assertNoSettingsContextCollision($definition, $settings, $context);

            try {
                $html .= $this->renderType($definition, array_merge($context, $settings));
            } catch (Throwable $exception) {
                // Actual Blade construction/rendering can still fail here
                // even with a schema-valid settings array (e.g. settings
                // that resolve fine but don't satisfy a stricter native
                // type Blade's own component instantiation enforces) -
                // isolated exactly like a resolution failure above.
                Log::warning('inlay: skipped a section that failed to render', [
                    'handle' => $section['handle'] ?? null,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $html;
    }

    /**
     * Renders one section and lets any failure propagate, for callers that
     * need to report a broken section rather than skip it.
     *
     * @param  array{handle?: string, settings?: array<string, mixed>}  $section
     */
    public function renderSection(array $section): string
    {
        return $this->renderDefinition($this->registry->definition($section['handle'] ?? ''), $section['settings'] ?? []);
    }

    /**
     * Renders a section definition that need not be registered - used to preview
     * an agent-authored section before it is saved.
     *
     * @param  array<string, mixed>  $stored
     */
    public function renderDefinition(Definition $definition, array $stored = []): string
    {
        return $this->renderType($definition, $this->resolveEffectiveSettings($definition, $stored));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $context
     */
    private function assertNoSettingsContextCollision(Definition $definition, array $settings, array $context): void
    {
        $colliding = array_intersect(array_keys($settings), array_keys($context));

        if ($colliding !== []) {
            throw new RuntimeException(sprintf(
                'Inlay: section [%s] declares setting(s) [%s] that collide with runtime $context key(s) of the same '
                .'name. Persisted settings and host-supplied context must never share a name - rename one side '
                .'rather than relying on either silently winning.',
                $definition->handle,
                implode(', ', $colliding),
            ));
        }
    }

    /**
     * Effective settings = current defaults merged with stored overrides.
     * The same algorithm handles a sparse draft (most keys absent, falling
     * through to the default) and an already fully-resolved published
     * snapshot (every key present, so the stored value always wins) -
     * docs/v1-implementation-specification.md §12-§13.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function resolveEffectiveSettings(Definition $definition, array $stored): array
    {
        return $this->resolveEffective($definition->settings, $definition->handle, $stored, raw: false);
    }

    /**
     * Same effective-settings algorithm, but normalised to JSON-safe
     * scalars/arrays (an enum resolves to its backing value, a link stays a
     * plain array) rather than hydrated PHP objects - for the editor's own
     * form-binding state, which Livewire must be able to serialise between
     * requests. The renderer's own construction path uses the hydrating
     * version above instead.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function resolveEffectiveRawSettings(Definition $definition, array $stored): array
    {
        return $this->resolveEffective($definition->settings, $definition->handle, $stored, raw: true);
    }

    /**
     * The same resolution for a schema that is not a section's, such as a
     * site settings group. $owner only identifies the schema in log context.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function resolveSchemaSettings(Settings $schema, string $owner, array $stored, bool $raw = false): array
    {
        return $this->resolveEffective($schema, $owner, $stored, $raw);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function resolveEffective(Settings $schema, string $owner, array $stored, bool $raw): array
    {
        $effective = [];

        foreach ($schema->fields() as $setting) {
            $effective[$setting->name] = match (true) {
                array_key_exists($setting->name, $stored) => $this->resolveValue($owner, $setting, $stored[$setting->name], $raw),
                $setting->type === FieldType::Blocks => $this->resolveValue($owner, $setting, is_array($setting->default) ? $setting->default : [], $raw),
                ! $raw && $this->hasPlainDefault($setting) => $this->resolveValue($owner, $setting, $setting->default, false),
                default => $this->normaliseDefault($setting, $raw),
            };
        }

        return $effective;
    }

    /**
     * A default declared as a plain value (a single-file section's '' image, a
     * repeater's array of items) rather than the value object a stored
     * setting hydrates to.
     */
    private function hasPlainDefault(Setting $setting): bool
    {
        return match ($setting->type) {
            FieldType::Image => is_string($setting->default),
            FieldType::Link, FieldType::Repeater, FieldType::Group => is_array($setting->default),
            default => false,
        };
    }

    private function normaliseDefault(Setting $setting, bool $raw): mixed
    {
        if ($raw && $setting->default instanceof BackedEnum) {
            return $setting->default->value;
        }

        if ($raw && $setting->default instanceof ImageValue) {
            return $setting->default->path;
        }

        if ($raw && $setting->default instanceof LinkValue) {
            return [
                'label' => $setting->default->label,
                'url' => $setting->default->url,
                'openInNewTab' => $setting->default->openInNewTab,
            ];
        }

        return $setting->default;
    }

    private function resolveValue(string $owner, Setting $setting, mixed $value, bool $raw): mixed
    {
        if ($setting->type === FieldType::Select && $setting->enumClass !== null) {
            $enum = $this->hydrateEnum($owner, $setting, $value);

            return $raw && $enum instanceof BackedEnum ? $enum->value : $enum;
        }

        if ($setting->type === FieldType::Blocks) {
            return $this->resolveBlocks($owner, is_array($value) ? $value : [], $raw);
        }

        if ($setting->type === FieldType::Link) {
            $link = $this->hydrateLink($value);

            return $raw ? ['label' => $link->label, 'url' => $link->url, 'openInNewTab' => $link->openInNewTab] : $link;
        }

        if ($setting->type === FieldType::Image) {
            return $this->hydrateImage($setting, $value, $raw);
        }

        if ($setting->type === FieldType::Repeater && $setting->itemSchema !== null && is_array($value)) {
            return array_map(
                fn (mixed $item): array => $this->resolveNestedEffectiveSettings($owner, $setting, is_array($item) ? $item : [], $raw),
                $value,
            );
        }

        if ($setting->type === FieldType::Group && $setting->itemSchema !== null) {
            return $this->resolveNestedEffectiveSettings($owner, $setting, is_array($value) ? $value : [], $raw);
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int, array{id: string, type: string, settings: array<string, mixed>}>|BlockList
     */
    private function resolveBlocks(string $owner, array $items, bool $raw): array|BlockList
    {
        $items = $this->registeredBlocks($items);

        return $raw ? $this->rawBlocks($items) : $this->renderBlocks($owner, $items);
    }

    /**
     * Normalises stored items to {id, type, settings}, dropping any whose
     * type is no longer registered. An item without an id (a declared
     * default) gets one from its position, so the editor can select it
     * before it is first saved.
     *
     * @param  array<int|string, mixed>  $items
     * @return array<int, array{id: string, type: string, settings: array<string, mixed>}>
     */
    private function registeredBlocks(array $items): array
    {
        $blocks = app(BlockRegistry::class);
        $registered = [];

        foreach (array_values($items) as $index => $item) {
            $type = is_array($item) ? ($item['type'] ?? null) : null;

            if (! is_string($type) || ! $blocks->hasHandle($type)) {
                continue;
            }

            $id = $item['id'] ?? null;

            $registered[] = [
                'id' => is_string($id) && $id !== '' ? $id : "b{$index}",
                'type' => $type,
                'settings' => is_array($item['settings'] ?? null) ? $item['settings'] : [],
            ];
        }

        return $registered;
    }

    /**
     * The editor's form state: each block's settings resolved over its
     * defaults, as plain data.
     *
     * @param  array<int, array{id: string, type: string, settings: array<string, mixed>}>  $items
     * @return array<int, array{id: string, type: string, settings: array<string, mixed>}>
     */
    private function rawBlocks(array $items): array
    {
        $raw = [];

        foreach ($items as $item) {
            $schema = app(BlockRegistry::class)->definition($item['type'])->settings;
            $item['settings'] = $this->resolveEffective($schema, "block:{$item['type']}", $item['settings'], raw: true);
            $raw[] = $item;
        }

        return $raw;
    }

    /**
     * A block that fails to render is logged and skipped, so one broken
     * block never takes down the section around it.
     *
     * @param  array<int, array{id: string, type: string, settings: array<string, mixed>}>  $items
     */
    private function renderBlocks(string $owner, array $items): BlockList
    {
        $rendered = [];

        foreach ($items as $item) {
            $definition = app(BlockRegistry::class)->definition($item['type']);

            try {
                $settings = $this->resolveEffective($definition->settings, "block:{$item['type']}", $item['settings'], raw: false);
                $html = $this->renderType($definition, $settings);
            } catch (Throwable $exception) {
                Log::warning('inlay: skipped a block that failed to render', [
                    'section' => $owner,
                    'block' => $item['type'],
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($this->tagForPreview) {
                $html = PreviewTag::apply($html, ['data-inlay-block' => $item['id'], 'data-inlay-block-label' => $definition->name]);
            }

            $rendered[] = new RenderedBlock($item['id'], $item['type'], $settings, $html);
        }

        return new BlockList($rendered);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function resolveNestedEffectiveSettings(string $owner, Setting $setting, array $stored, bool $raw): array
    {
        $itemSchema = $setting->itemSchema;

        $effective = [];

        foreach ($itemSchema->fields() as $nested) {
            $default = $nested->hasNestedDefault ? $nested->nestedDefault : null;

            $effective[$nested->name] = array_key_exists($nested->name, $stored)
                ? $this->resolveValue($owner, $nested, $stored[$nested->name], $raw)
                : $default;
        }

        return $effective;
    }

    /**
     * An orphaned enum value (a stored value that no longer matches any case,
     * because a case was removed after content referencing it was created)
     * falls back to the constructor default and logs a structured warning
     * for public rendering - docs/v1-implementation-specification.md §10.
     */
    private function hydrateEnum(string $owner, Setting $setting, mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            $case = ($setting->enumClass)::tryFrom($value);

            if ($case !== null) {
                return $case;
            }
        }

        Log::warning('inlay: stored value no longer matches a valid enum case, using the constructor default', [
            'section' => $owner,
            'setting' => $setting->name,
            'enum' => $setting->enumClass,
            'value' => $value,
        ]);

        return $setting->default;
    }

    private function hydrateLink(mixed $value): LinkValue
    {
        if ($value instanceof LinkValue) {
            return $value;
        }

        $value = is_array($value) ? $value : [];

        return new LinkValue(
            label: is_string($value['label'] ?? null) ? $value['label'] : '',
            url: is_string($value['url'] ?? null) ? $value['url'] : '',
            openInNewTab: is_scalar($value['openInNewTab'] ?? null) ? (bool) $value['openInNewTab'] : false,
        );
    }

    /**
     * The raw form (the editor's own editingProps) always stays the bare
     * disk-relative path string - Livewire has to serialise it, and the
     * inspector's own image field partial resolves that path to a URL
     * itself for its thumbnail. Only real rendering hydrates it into an
     * ImageValue, and only when there's actually a path to resolve - a
     * setting explicitly declared nullable (`?ImageValue $x = null`) gets a
     * real null for "no image" rather than an always-truthy empty instance,
     * matching what a section author would naturally write `@if ($x)` for.
     */
    private function hydrateImage(Setting $setting, mixed $value, bool $raw): string|ImageValue|null
    {
        $path = is_string($value) ? $value : '';

        if ($raw) {
            return $path;
        }

        if ($path === '' && $setting->nullable) {
            return null;
        }

        return new ImageValue($path);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function renderType(Definition $definition, array $settings): string
    {
        return match ($definition->sourceType) {
            SourceType::ClassBased => $this->renderClassBased($definition->sourceIdentifier, $settings),
            SourceType::SingleFile => $this->renderSingleFile($definition->sourceIdentifier, $settings),
            SourceType::Declarative => app(DeclarativeRenderer::class)->render($definition->template ?? [], $settings),
        };
    }

    /**
     * @param  class-string  $sectionClass
     * @param  array<string, mixed>  $settings
     */
    private function renderClassBased(string $sectionClass, array $settings): string
    {
        $alias = 'inlay-'.md5($sectionClass);

        // <x-dynamic-component> does not accept a fully-qualified class name
        // directly (confirmed against this package's pinned Laravel version -
        // see the spike test); it must be resolved through a registered
        // component alias instead. Registering is idempotent and cheap enough
        // to call on every render.
        Blade::component($alias, $sectionClass);

        $bindings = collect(array_keys($settings))
            ->map(fn (string $key) => ":{$key}=\"\$settings['{$key}']\"")
            ->implode(' ');

        // Deliberately not <x-dynamic-component :component="..."> here.
        // Illuminate\View\DynamicComponent memoises its ComponentTagCompiler
        // and every resolved class into `protected static` properties that
        // live for the entire PHP process - fine for one long-running
        // application, but the memoised compiler snapshots whatever aliases
        // existed at the *first* dynamic-component render ever, in that
        // process, and never refreshes, so an alias registered afterward
        // (a second section's class, a fresh application boot in a test
        // suite, a queued worker that renders a section type it hasn't
        // seen yet) silently fails to resolve. Confirmed empirically by this
        // package's own spike (tests/Feature/RendererMechanismSpikeTest.php)
        // failing only when run after another test had already rendered a
        // different aliased section in the same process.
        //
        // The alias is interpolated directly as a literal tag name instead
        // (<x-{$alias}>), which Blade compiles as an ordinary, non-dynamic
        // component tag against the live compiler instance at compile time -
        // no static caching, no process-lifetime staleness.
        //
        // Blade::render() also caches its compiled output keyed by the
        // template TEXT, not by data - two different aliases can produce
        // byte-identical template text (same binding variable names,
        // different values), so deleteCachedView forces a fresh compile
        // every render rather than risking a stale compiled file from a
        // different alias's resolution.
        return Blade::render(
            "<x-{$alias} {$bindings} />",
            ['settings' => $settings],
            deleteCachedView: true,
        );
    }

    /**
     * A single-file section has no class or alias to render through - its
     * own Blade file is rendered directly, via Laravel's real view engine
     * (View::file() compiles an arbitrary file path exactly like a
     * registered view). $settings are extracted into the view's scope by
     * the same mechanism any Blade view's data is - no manual variable
     * assignment, no eval().
     *
     * @param  array<string, mixed>  $settings
     */
    private function renderSingleFile(string $bladeFilePath, array $settings): string
    {
        return View::file($bladeFilePath, $settings)->render();
    }
}
