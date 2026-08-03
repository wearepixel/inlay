<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use BackedEnum;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Throwable;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;

/**
 * Renders each registered block through Laravel's real view engine -
 * a class-based block through a genuine, compiled Blade component tag
 * (<x-dynamic-component> resolved via a registered alias, per the mechanism
 * proven in tests/Feature/RendererMechanismSpikeTest.php and documented in
 * docs/v1-implementation-specification.md §5); a single-file block
 * through View::file() against its own Blade file directly. Both paths are
 * real Blade compilation, never manual `new $blockClass(...)`
 * construction - which is what lets either kind's own view freely nest a
 * genuine, hydrating Livewire component with zero awareness on the
 * package's part.
 */
final class SectionRenderer
{
    public function __construct(
        private readonly BlockRegistry $registry,
    ) {}

    /**
     * A public entry point a host application can call directly with its
     * own hand-built iterable, so a missing key is a real possibility here,
     * not just an internal-consistency guarantee - handle/settings are
     * defensively treated as optional rather than assumed present.
     *
     * `$context` is host-supplied, request-specific data with no schema
     * Inlay understands (the collection currently being viewed, the
     * authenticated customer) - a block whose Blade view declares a
     * matching property picks it up the same way it already picks up an
     * ordinary container-resolvable dependency (docs/v1-implementation-
     * specification.md §9). Inlay itself never inspects or validates its
     * values, never persists it, and never includes it in a revision
     * snapshot - PublishPage resolves and freezes $settings alone.
     *
     * $settings and $context are kept as two explicit, never-merged-blind
     * concepts rather than one combined array: if a block declares a
     * setting with the same name as a $context key, that's a genuine
     * developer naming conflict with no correct silent resolution (does
     * the persisted setting win, or the live context - a wrong guess
     * either way is a real bug that would only surface once someone
     * actually supplies that context), so it fails loudly here instead -
     * see assertNoSettingsContextCollision(). This check runs outside the
     * per-block try/catch below on purpose: it is a configuration error,
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
                // A malformed or unresolvable block must not take down
                // every other block on the page.
                Log::warning('inlay: skipped a block that failed to render', [
                    'handle' => $section['handle'] ?? null,
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            $this->assertNoSettingsContextCollision($definition, $settings, $context);

            try {
                $html .= $this->renderBlock($definition, array_merge($context, $settings));
            } catch (Throwable $exception) {
                // Actual Blade construction/rendering can still fail here
                // even with a schema-valid settings array (e.g. settings
                // that resolve fine but don't satisfy a stricter native
                // type Blade's own component instantiation enforces) -
                // isolated exactly like a resolution failure above.
                Log::warning('inlay: skipped a block that failed to render', [
                    'handle' => $section['handle'] ?? null,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $context
     */
    private function assertNoSettingsContextCollision(BlockDefinition $definition, array $settings, array $context): void
    {
        $colliding = array_intersect(array_keys($settings), array_keys($context));

        if ($colliding !== []) {
            throw new RuntimeException(sprintf(
                'Inlay: block [%s] declares setting(s) [%s] that collide with runtime $context key(s) of the same '
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
    public function resolveEffectiveSettings(BlockDefinition $definition, array $stored): array
    {
        return $this->resolveEffective($definition, $stored, raw: false);
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
    public function resolveEffectiveRawSettings(BlockDefinition $definition, array $stored): array
    {
        return $this->resolveEffective($definition, $stored, raw: true);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function resolveEffective(BlockDefinition $definition, array $stored, bool $raw): array
    {
        $effective = [];

        foreach ($definition->settings->fields() as $setting) {
            $effective[$setting->name] = array_key_exists($setting->name, $stored)
                ? $this->resolveValue($definition, $setting, $stored[$setting->name], $raw)
                : $this->normaliseDefault($setting, $raw);
        }

        return $effective;
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

    private function resolveValue(BlockDefinition $definition, Setting $setting, mixed $value, bool $raw): mixed
    {
        if ($setting->type === FieldType::Select && $setting->enumClass !== null) {
            $enum = $this->hydrateEnum($definition, $setting, $value);

            return $raw && $enum instanceof BackedEnum ? $enum->value : $enum;
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
                fn (mixed $item): array => $this->resolveNestedEffectiveSettings($definition, $setting, is_array($item) ? $item : [], $raw),
                $value,
            );
        }

        if ($setting->type === FieldType::Group && $setting->itemSchema !== null) {
            return $this->resolveNestedEffectiveSettings($definition, $setting, is_array($value) ? $value : [], $raw);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function resolveNestedEffectiveSettings(BlockDefinition $definition, Setting $setting, array $stored, bool $raw): array
    {
        $itemSchema = $setting->itemSchema;

        $effective = [];

        foreach ($itemSchema->fields() as $nested) {
            $default = $nested->hasNestedDefault ? $nested->nestedDefault : null;

            $effective[$nested->name] = array_key_exists($nested->name, $stored)
                ? $this->resolveValue($definition, $nested, $stored[$nested->name], $raw)
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
    private function hydrateEnum(BlockDefinition $definition, Setting $setting, mixed $value): mixed
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
            'block' => $definition->handle,
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
     * matching what a block author would naturally write `@if ($x)` for.
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
    private function renderBlock(BlockDefinition $definition, array $settings): string
    {
        return match ($definition->sourceType) {
            BlockSourceType::ClassBased => $this->renderClassBasedBlock($definition->sourceIdentifier, $settings),
            BlockSourceType::SingleFile => $this->renderSingleFileBlock($definition->sourceIdentifier, $settings),
        };
    }

    /**
     * @param  class-string  $blockClass
     * @param  array<string, mixed>  $settings
     */
    private function renderClassBasedBlock(string $blockClass, array $settings): string
    {
        $alias = 'inlay-'.md5($blockClass);

        // <x-dynamic-component> does not accept a fully-qualified class name
        // directly (confirmed against this package's pinned Laravel version -
        // see the spike test); it must be resolved through a registered
        // component alias instead. Registering is idempotent and cheap enough
        // to call on every render.
        Blade::component($alias, $blockClass);

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
        // (a second block's class, a fresh application boot in a test
        // suite, a queued worker that renders a block type it hasn't
        // seen yet) silently fails to resolve. Confirmed empirically by this
        // package's own spike (tests/Feature/RendererMechanismSpikeTest.php)
        // failing only when run after another test had already rendered a
        // different aliased block in the same process.
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
     * A single-file block has no class or alias to render through - its
     * own Blade file is rendered directly, via Laravel's real view engine
     * (View::file() compiles an arbitrary file path exactly like a
     * registered view). $settings are extracted into the view's scope by
     * the same mechanism any Blade view's data is - no manual variable
     * assignment, no eval().
     *
     * @param  array<string, mixed>  $settings
     */
    private function renderSingleFileBlock(string $bladeFilePath, array $settings): string
    {
        return View::file($bladeFilePath, $settings)->render();
    }
}
