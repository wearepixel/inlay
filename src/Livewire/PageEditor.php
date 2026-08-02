<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Livewire;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use WearePixel\Inlay\Actions\PublishPage;
use WearePixel\Inlay\Actions\RestorePageRevision;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Models\PageSection;
use WearePixel\Inlay\Schema\FieldType;
use WearePixel\Inlay\Schema\Setting;
use WearePixel\Inlay\Schema\Settings;
use WearePixel\Inlay\Support\ComponentDefinitionResolver;
use WearePixel\Inlay\Support\SectionRegistry;
use WearePixel\Inlay\Support\SectionRenderer;

#[Layout('inlay::layout')]
final class PageEditor extends Component
{
    use WithFileUploads;

    public Page $page;

    public ?int $selectedSectionId = null;

    public bool $showingHistory = false;

    public bool $showingPicker = false;

    /**
     * Bumped on every action that changes what the page renders, and used
     * only as a query-string cache-buster on the preview iframe's src so a
     * settings change always forces a fresh, real HTTP render of the
     * host application's own frontend rather than a stale cached one -
     * docs/v1-implementation-specification.md §18.
     */
    public int $previewVersion = 0;

    /** @var array<string, mixed> */
    public array $editingProps = [];

    /** @var array<string, mixed> */
    public array $imageUploads = [];

    public function mount(Page $page): void
    {
        $this->page = $page;
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function updateOrder(array $orderedIds): void
    {
        // Every update is already scoped to this page's own rows, so a
        // crafted id can never touch another page's sections - but without
        // this, an arbitrarily large client-supplied array would still
        // issue one bounded-but-real UPDATE per element. Clamp to this
        // page's actual section ids first.
        $ownedIds = $this->page->sections()->pluck('id')->all();
        $orderedIds = array_values(array_intersect(array_map('intval', $orderedIds), $ownedIds));

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                PageSection::whereKey($id)
                    ->where('page_id', $this->page->id)
                    ->update(['order' => $index]);
            }
        });

        $this->bumpPreview();
    }

    public function openPicker(): void
    {
        $this->showingPicker = true;
    }

    public function closePicker(): void
    {
        $this->showingPicker = false;
    }

    public function addSection(string $slug): void
    {
        $componentClass = app(SectionRegistry::class)->resolve($slug);

        $section = $this->page->sections()->create([
            'handle' => $slug,
            'order' => (int) ($this->page->sections()->max('order') + 1),
            // Deliberately sparse: a freshly-added component stores no
            // overrides at all. Its fields resolve entirely from the
            // component's own constructor defaults until an editor changes
            // one - docs/v1-implementation-specification.md §12.
            'settings' => [],
        ]);

        $this->selectedSectionId = $section->id;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($componentClass, []);
        $this->showingPicker = false;
        $this->bumpPreview();
    }

    public function duplicateSection(int $sectionId): void
    {
        $section = PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->firstOrFail();

        $duplicate = $this->page->sections()->create([
            'handle' => $section->handle,
            'order' => (int) ($this->page->sections()->max('order') + 1),
            'settings' => $section->settings,
        ]);

        $componentClass = app(SectionRegistry::class)->resolve($duplicate->handle);

        $this->selectedSectionId = $duplicate->id;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($componentClass, $duplicate->settings ?? []);
        $this->bumpPreview();
    }

    public function deleteSection(int $sectionId): void
    {
        PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->delete();

        if ($this->selectedSectionId === $sectionId) {
            $this->selectedSectionId = null;
            $this->editingProps = [];
        }

        $this->bumpPreview();
    }

    public function selectSection(int $sectionId): void
    {
        $section = PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->firstOrFail();

        $componentClass = app(SectionRegistry::class)->resolve($section->handle);

        $this->selectedSectionId = $sectionId;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($componentClass, $section->settings ?? []);
    }

    public function addRepeaterItem(string $fieldName): void
    {
        $this->editingProps[$fieldName] ??= [];
        $this->editingProps[$fieldName][] = [];
        $this->persistSetting($fieldName);
    }

    public function removeRepeaterItem(string $fieldName, int $index): void
    {
        unset($this->editingProps[$fieldName][$index]);
        $this->editingProps[$fieldName] = array_values($this->editingProps[$fieldName] ?? []);
        $this->persistSetting($fieldName);
    }

    /**
     * Livewire reports exactly which nested path changed via $key (e.g.
     * "heading" or "cta.label") - used here to persist only the top-level
     * setting that was actually touched, not the whole editingProps array.
     * Persisting everything on every keystroke would write every field as an
     * explicit override the moment any single field changed, defeating
     * sparse storage entirely.
     */
    public function updatedEditingProps(mixed $value, string $key): void
    {
        $this->persistSetting(explode('.', $key)[0]);
    }

    public function updatedImageUploads(mixed $value, string $key): void
    {
        if (! $value instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate([
            "imageUploads.{$key}" => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $value->store('inlay', config('inlay.disk', 'public'));

        $this->editingProps[$key] = $path;
        $this->persistSetting($key);
    }

    public function publish(): void
    {
        app(PublishPage::class)->execute($this->page, auth()->id());
        $this->dispatch('page-published');
    }

    public function toggleHistory(): void
    {
        $this->showingHistory = ! $this->showingHistory;
    }

    public function restore(int $revisionId): void
    {
        $revision = $this->page->revisions()->findOrFail($revisionId);

        app(RestorePageRevision::class)->execute($revision, auth()->id());

        $this->selectedSectionId = null;
        $this->editingProps = [];
        $this->showingHistory = false;
        $this->bumpPreview();
    }

    public function render(): View
    {
        return view('inlay::livewire.page-editor', [
            'title' => "{$this->page->title} · Inlay",
            'sections' => $this->page->sections()->orderBy('order')->get(),
            'availableComponents' => collect(app(SectionRegistry::class)->all())->groupBy(fn ($component) => $component->category),
            'selectedSchema' => $this->resolveSelectedSettings(),
            'previewUrl' => $this->previewUrl(),
            'revisions' => $this->page->revisions()->get(),
        ]);
    }

    private function previewUrl(): string
    {
        return route('inlay.pages.preview', $this->page).'?v='.$this->previewVersion;
    }

    private function bumpPreview(): void
    {
        $this->previewVersion++;
    }

    private function resolveSelectedSettings(): ?Settings
    {
        if (! $this->selectedSectionId) {
            return null;
        }

        $section = PageSection::whereKey($this->selectedSectionId)
            ->where('page_id', $this->page->id)
            ->first();

        if (! $section) {
            return null;
        }

        $componentClass = app(SectionRegistry::class)->resolve($section->handle);

        return app(ComponentDefinitionResolver::class)->resolve($componentClass);
    }

    /**
     * Persists exactly one top-level setting as an explicit override, merged
     * into whatever was already stored for this section - never the whole
     * editingProps array. This is the security boundary as much as it is the
     * sparse-storage mechanism: a setting with no matching declaration in the
     * component's resolved definition is silently ignored here, so an
     * editor can never persist a key a developer didn't expose, and
     * coercion always runs unconditionally, regardless of that field's
     * current visibleWhen() state.
     */
    private function persistSetting(string $fieldName): void
    {
        if (! $this->selectedSectionId) {
            return;
        }

        $section = PageSection::whereKey($this->selectedSectionId)
            ->where('page_id', $this->page->id)
            ->first();

        if (! $section) {
            return;
        }

        $componentClass = app(SectionRegistry::class)->resolve($section->handle);
        $definition = app(ComponentDefinitionResolver::class)->resolve($componentClass);
        $setting = $definition->field($fieldName);

        if (! $setting) {
            return;
        }

        $coerced = $this->coerceValue($setting, $this->editingProps[$fieldName] ?? null);
        $this->editingProps[$fieldName] = $coerced;

        $stored = $section->settings ?? [];
        $stored[$fieldName] = $coerced;

        $section->update(['settings' => $stored]);
        $this->bumpPreview();
    }

    /**
     * Livewire public properties are settable by the client for any nested
     * key, not just the ones rendered by the schema-driven form. Coerce every
     * declared field's value to the shape its FieldType expects before it
     * reaches storage - a declared scalar field can still be set to an array
     * via the same wire:model mechanism, which would otherwise reach the
     * component constructor and throw a fatal TypeError.
     */
    private function coerceValue(Setting $setting, mixed $value): mixed
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
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function coerceNested(Settings $schema, array $values): array
    {
        $result = [];

        foreach ($schema->fields() as $nested) {
            if (! array_key_exists($nested->name, $values)) {
                continue;
            }

            $result[$nested->name] = $this->coerceValue($nested, $values[$nested->name]);
        }

        return $result;
    }

    /**
     * The Link field's Blade partial binds `label`, `url`, and `openInNewTab`
     * as separate sub-paths, so its value is a structured array rather than
     * a plain scalar. Validate its own sub-keys instead of coercing the
     * whole value to a string.
     *
     * @return array{label: string, url: string, openInNewTab: bool}
     */
    private function coerceLinkValue(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        $url = is_string($value['url'] ?? null) ? $value['url'] : '';

        return [
            'label' => is_string($value['label'] ?? null) ? $value['label'] : '',
            'url' => $this->isAllowedUrlScheme($url) ? $url : '',
            'openInNewTab' => is_scalar($value['openInNewTab'] ?? null) ? (bool) $value['openInNewTab'] : false,
        ];
    }

    /**
     * Consuming apps render the Link field's `url` directly into an `href`
     * attribute. Blade's HTML-attribute escaping neutralises HTML-special
     * characters but not a dangerous URI scheme, so a value like
     * `javascript:alert(1)` would otherwise persist and execute on click.
     * Allow only http(s), mailto, and relative URLs (scheme-less).
     */
    private function isAllowedUrlScheme(string $url): bool
    {
        if (! str_contains($url, ':')) {
            return true;
        }

        return (bool) preg_match('/^(https?|mailto):/i', $url);
    }

    /**
     * A stored value that no longer matches a real case (an enum case was
     * removed) falls back to the constructor default here too, so the
     * editor's own form state never holds a value the renderer would reject.
     */
    private function coerceEnumValue(Setting $setting, mixed $value): string
    {
        $enumClass = $setting->enumClass;

        $candidate = $value instanceof BackedEnum ? $value->value : $value;

        if (is_string($candidate) && $enumClass::tryFrom($candidate) !== null) {
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

    /**
     * Text, Textarea, Richtext, Image, and non-enum Select fields all bind to
     * a single scalar model path. Reject non-scalar values (arrays, objects)
     * that a client could otherwise inject via wire:model rather than
     * passing them through unchecked.
     */
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
