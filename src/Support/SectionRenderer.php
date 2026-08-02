<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

use BackedEnum;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Throwable;
use WearePixel\Inlay\Schema\FieldType;
use WearePixel\Inlay\Schema\Setting;

/**
 * Renders each registered component through a real, compiled Blade view -
 * <x-dynamic-component> resolved via a registered component alias, per the
 * mechanism proven in tests/Feature/RendererMechanismSpikeTest.php and
 * documented in docs/v1-implementation-specification.md §5.
 *
 * This is not manual `new $componentClass(...)` construction: rendering
 * through Blade's real component-tag compiler is what lets a component's own
 * view freely nest a genuine, hydrating Livewire component with zero
 * awareness on the package's part.
 */
final class SectionRenderer
{
    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly ComponentDefinitionResolver $resolver = new ComponentDefinitionResolver,
    ) {}

    /**
     * A public entry point a host application can call directly with its
     * own hand-built iterable, so a missing key is a real possibility here,
     * not just an internal-consistency guarantee - handle/settings are
     * defensively treated as optional rather than assumed present.
     *
     * @param  iterable<array{handle?: string, settings?: array<string, mixed>}>  $sections
     */
    public function render(iterable $sections): string
    {
        $html = '';

        foreach ($sections as $section) {
            try {
                $componentClass = $this->registry->resolve($section['handle'] ?? '');
                $settings = $this->resolveEffectiveSettings($componentClass, $section['settings'] ?? []);

                $html .= $this->renderComponent($componentClass, $settings);
            } catch (Throwable $exception) {
                // A malformed or unresolvable component must not take down
                // every other component on the page.
                Log::warning('inlay: skipped a component that failed to render', [
                    'handle' => $section['handle'] ?? null,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $html;
    }

    /**
     * Effective settings = current defaults merged with stored overrides.
     * The same algorithm handles a sparse draft (most keys absent, falling
     * through to the default) and an already fully-resolved published
     * snapshot (every key present, so the stored value always wins) -
     * docs/v1-implementation-specification.md §12-§13.
     *
     * @param  class-string  $componentClass
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function resolveEffectiveSettings(string $componentClass, array $stored): array
    {
        return $this->resolveEffective($componentClass, $stored, raw: false);
    }

    /**
     * Same effective-settings algorithm, but normalised to JSON-safe
     * scalars/arrays (an enum resolves to its backing value, a link stays a
     * plain array) rather than hydrated PHP objects - for the editor's own
     * form-binding state, which Livewire must be able to serialise between
     * requests. The renderer's own construction path uses the hydrating
     * version above instead.
     *
     * @param  class-string  $componentClass
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function resolveEffectiveRawSettings(string $componentClass, array $stored): array
    {
        return $this->resolveEffective($componentClass, $stored, raw: true);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function resolveEffective(string $componentClass, array $stored, bool $raw): array
    {
        $definition = $this->resolver->resolve($componentClass);

        $effective = [];

        foreach ($definition->fields() as $setting) {
            $effective[$setting->name] = array_key_exists($setting->name, $stored)
                ? $this->resolveValue($componentClass, $setting, $stored[$setting->name], $raw)
                : $this->normaliseDefault($setting, $raw);
        }

        return $effective;
    }

    private function normaliseDefault(Setting $setting, bool $raw): mixed
    {
        if ($raw && $setting->default instanceof BackedEnum) {
            return $setting->default->value;
        }

        return $setting->default;
    }

    private function resolveValue(string $componentClass, Setting $setting, mixed $value, bool $raw): mixed
    {
        if ($setting->type === FieldType::Select && $setting->enumClass !== null) {
            $enum = $this->hydrateEnum($componentClass, $setting, $value);

            return $raw && $enum instanceof BackedEnum ? $enum->value : $enum;
        }

        if ($setting->type === FieldType::Link) {
            $link = $this->hydrateLink($value);

            return $raw ? ['label' => $link->label, 'url' => $link->url, 'openInNewTab' => $link->openInNewTab] : $link;
        }

        if ($setting->type === FieldType::Repeater && $setting->itemSchema !== null && is_array($value)) {
            return array_map(
                fn (mixed $item): array => $this->resolveNestedEffectiveSettings($componentClass, $setting, is_array($item) ? $item : [], $raw),
                $value,
            );
        }

        if ($setting->type === FieldType::Group && $setting->itemSchema !== null) {
            return $this->resolveNestedEffectiveSettings($componentClass, $setting, is_array($value) ? $value : [], $raw);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function resolveNestedEffectiveSettings(string $componentClass, Setting $setting, array $stored, bool $raw): array
    {
        $itemSchema = $setting->itemSchema;

        $effective = [];

        foreach ($itemSchema->fields() as $nested) {
            $default = $nested->hasNestedDefault ? $nested->nestedDefault : null;

            $effective[$nested->name] = array_key_exists($nested->name, $stored)
                ? $this->resolveValue($componentClass, $nested, $stored[$nested->name], $raw)
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
    private function hydrateEnum(string $componentClass, Setting $setting, mixed $value): mixed
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
            'component' => $componentClass,
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
     * @param  array<string, mixed>  $settings
     */
    private function renderComponent(string $componentClass, array $settings): string
    {
        $alias = 'inlay-'.md5($componentClass);

        // <x-dynamic-component> does not accept a fully-qualified class name
        // directly (confirmed against this package's pinned Laravel version -
        // see the spike test); it must be resolved through a registered
        // component alias instead. Registering is idempotent and cheap enough
        // to call on every render.
        Blade::component($alias, $componentClass);

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
        // (a second component class, a fresh application boot in a test
        // suite, a queued worker that renders a component type it hasn't
        // seen yet) silently fails to resolve. Confirmed empirically by this
        // package's own spike (tests/Feature/RendererMechanismSpikeTest.php)
        // failing only when run after another test had already rendered a
        // different aliased component in the same process.
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
}
