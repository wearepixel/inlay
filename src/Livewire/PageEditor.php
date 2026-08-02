<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Livewire;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Actions\RestorePageRevision;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\ComponentDefinition;
use WeArePixel\Inlay\Support\SectionInstanceLabel;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;

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
        $definition = app(SectionRegistry::class)->definition($slug);

        $section = $this->page->sections()->create([
            'handle' => $slug,
            'order' => (int) ($this->page->sections()->max('order') + 1),
            // Deliberately sparse: a freshly-added component stores no
            // overrides at all. Its fields resolve entirely from the
            // component's own defaults until an editor changes one -
            // docs/v1-implementation-specification.md §12.
            'settings' => [],
        ]);

        $this->selectedSectionId = $section->id;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($definition, []);
        $this->showingPicker = false;
        $this->bumpPreview();

        // The picker has no way to optimistically switch the contextual
        // sidebar to this brand new section's settings client-side - unlike
        // a tree row click, its id doesn't exist until this method creates
        // it - so the client learns which one to show from this event.
        $this->dispatch('section-selected', id: $section->id);
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

        $definition = app(SectionRegistry::class)->definition($duplicate->handle);

        $this->selectedSectionId = $duplicate->id;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($definition, $duplicate->settings ?? []);
        $this->bumpPreview();

        // Same reasoning as addSection() - the duplicate's id doesn't exist
        // client-side until this method creates it.
        $this->dispatch('section-selected', id: $duplicate->id);
    }

    public function deleteSection(int $sectionId): void
    {
        PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->delete();

        if ($this->selectedSectionId === $sectionId) {
            $this->selectedSectionId = null;
            $this->editingProps = [];
            $this->dispatch('selection-cleared');
        }

        $this->bumpPreview();
    }

    public function selectSection(int $sectionId): void
    {
        $section = PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->firstOrFail();

        $definition = app(SectionRegistry::class)->definition($section->handle);

        $this->selectedSectionId = $sectionId;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($definition, $section->settings ?? []);
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

        // The contextual sidebar has no server-rendered "history" state of
        // its own to react to - it decides what to show entirely
        // client-side (see inlayEditorSidebar in inlay.js), so it needs to
        // be told explicitly rather than inferring this from a prop change.
        $this->dispatch('history-toggled', showing: $this->showingHistory);
    }

    public function restore(int $revisionId): void
    {
        $revision = $this->page->revisions()->findOrFail($revisionId);

        app(RestorePageRevision::class)->execute($revision, auth()->id());

        $this->selectedSectionId = null;
        $this->editingProps = [];
        $this->showingHistory = false;
        $this->bumpPreview();
        $this->dispatch('revision-restored');
        $this->dispatch('selection-cleared');
    }

    public function render(): View
    {
        $sections = $this->page->sections()->orderBy('order')->get();
        $selectedSection = $this->selectedSectionId
            ? $sections->firstWhere('id', $this->selectedSectionId)
            : null;

        if ($this->selectedSectionId && ! $selectedSection) {
            // The selected component no longer exists - deleted from
            // another tab or session, since this session's own delete and
            // restore() already clear selectedSectionId themselves before
            // reaching here. Fall back to the component tree rather than
            // rendering a settings pane for a component that's gone.
            $this->selectedSectionId = null;
            $this->editingProps = [];
            $this->dispatch('selection-cleared');
        }

        $selectedDefinition = $selectedSection
            ? app(SectionRegistry::class)->definition($selectedSection->handle)
            : null;

        $schema = $selectedDefinition?->settings;
        $components = collect(app(SectionRegistry::class)->all());

        return view('inlay::livewire.page-editor', [
            'title' => "{$this->page->title} · Inlay",
            'sections' => $sections,
            // Display name + a best-guess instance detail (e.g. "Hero" /
            // "Welcome") for every section in the tree - see
            // SectionInstanceLabel. Keyed by section id so the view doesn't
            // need to re-resolve each component's definition itself.
            'sectionLabels' => $sections->mapWithKeys(fn (PageSection $section) => [
                $section->id => $this->instanceLabel(
                    app(SectionRegistry::class)->definition($section->handle),
                    $section->settings ?? [],
                ),
            ]),
            'selectedInstance' => $selectedDefinition
                ? $this->instanceLabel($selectedDefinition, $selectedSection->settings ?? [])
                : null,
            'availableComponents' => $components->groupBy(fn ($component) => $component->category),
            // A slug => {label, icon} lookup for the component picker's
            // "recent" row, which tracks recently-added handles client-side
            // (localStorage) and needs enough metadata to render a compact
            // chip for one without a further round trip.
            'componentLookup' => $components->mapWithKeys(fn ($component) => [
                $component->slug => ['label' => $component->label, 'icon' => $component->icon],
            ]),
            'selectedSchema' => $schema,
            'inspectorTabs' => $this->inspectorTabs($schema),
            'previewUrl' => $this->previewUrl(),
            'revisions' => $this->page->revisions()->get(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $storedSettings
     * @return array{name: string, detail: ?string}
     */
    private function instanceLabel(ComponentDefinition $definition, array $storedSettings): array
    {
        return [
            'name' => $definition->name,
            'detail' => SectionInstanceLabel::detail($definition, $storedSettings),
        ];
    }

    /**
     * The settings inspector's tab > group > field structure, computed once
     * here rather than with nested loops in the view. A component that
     * never calls Settings::tab() ends up with exactly one tab (label
     * null), which the view renders flat with no tab bar - tab navigation
     * only ever appears once a component actually declares more than one.
     *
     * @return Collection<int, array{label: string|null, groups: Collection<int, array{label: string|null, fields: Collection<int, Setting>}>}>
     */
    private function inspectorTabs(?Settings $schema): Collection
    {
        $tabs = collect();

        if (! $schema) {
            return $tabs;
        }

        foreach (collect($schema->fields())->groupBy(fn (Setting $field) => $field->tab ?? '') as $tabKey => $fields) {
            $groups = collect();

            foreach ($fields->groupBy(fn (Setting $field) => $field->group ?? '') as $groupKey => $groupFields) {
                $groups->push([
                    'label' => $this->blankToNull((string) $groupKey),
                    'fields' => $groupFields->values(),
                ]);
            }

            $tabs->push([
                'label' => $this->blankToNull((string) $tabKey),
                'groups' => $groups,
            ]);
        }

        return $tabs;
    }

    /**
     * A native ?string return type, deliberately not narrower - the caller
     * cares only that a blank key means "no tab/group", not that a
     * non-blank one is provably non-empty.
     */
    private function blankToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function previewUrl(): string
    {
        return route('inlay.pages.preview', $this->page).'?v='.$this->previewVersion;
    }

    private function bumpPreview(): void
    {
        $this->previewVersion++;
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

        $definition = app(SectionRegistry::class)->definition($section->handle);
        $setting = $definition->settings->field($fieldName);

        if (! $setting) {
            return;
        }

        $coerced = $this->coerceValue($setting, $this->editingProps[$fieldName] ?? null);
        $this->editingProps[$fieldName] = $coerced;

        $stored = $section->settings ?? [];
        $stored[$fieldName] = $coerced;

        $section->update(['settings' => $stored]);
        $this->bumpPreview();
        $this->dispatch('setting-saved');
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
