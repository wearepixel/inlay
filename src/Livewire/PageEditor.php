<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Actions\RestorePageRevision;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Models\PageSection;
use WearePixel\BladePageBuilder\Schema\FieldType;
use WearePixel\BladePageBuilder\Schema\Schema;
use WearePixel\BladePageBuilder\Support\SectionRegistry;
use WearePixel\BladePageBuilder\Support\SectionRenderer;

final class PageEditor extends Component
{
    use WithFileUploads;

    public Page $page;

    public ?int $selectedSectionId = null;

    public bool $showingHistory = false;

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
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                PageSection::whereKey($id)
                    ->where('page_id', $this->page->id)
                    ->update(['order' => $index]);
            }
        });
    }

    public function addSection(string $slug): void
    {
        $componentClass = app(SectionRegistry::class)->resolve($slug);

        $props = [];
        foreach ($componentClass::schema()->fields() as $field) {
            $props[$field->name] = in_array($field->type, [FieldType::Repeater, FieldType::Group], true) ? [] : '';
        }

        $section = $this->page->sections()->create([
            'section_type' => $slug,
            'order' => (int) ($this->page->sections()->max('order') + 1),
            'props' => $props,
        ]);

        $this->selectedSectionId = $section->id;
        $this->editingProps = $props;
    }

    public function selectSection(int $sectionId): void
    {
        $section = PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->firstOrFail();

        $schema = app(SectionRegistry::class)->resolve($section->section_type)::schema();

        $this->selectedSectionId = $sectionId;
        $this->editingProps = $this->filterPropsAgainstSchema($section->props ?? [], $schema);
    }

    public function addRepeaterItem(string $fieldName): void
    {
        $this->editingProps[$fieldName] ??= [];
        $this->editingProps[$fieldName][] = [];
        $this->persistEditingProps();
    }

    public function removeRepeaterItem(string $fieldName, int $index): void
    {
        unset($this->editingProps[$fieldName][$index]);
        $this->editingProps[$fieldName] = array_values($this->editingProps[$fieldName] ?? []);
        $this->persistEditingProps();
    }

    public function updatedEditingProps(): void
    {
        $this->persistEditingProps();
    }

    public function updatedImageUploads(mixed $value, string $key): void
    {
        if (! $value instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate([
            "imageUploads.{$key}" => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $value->store('page-builder', config('blade-page-builder.disk', 'public'));

        $this->editingProps[$key] = $path;
        $this->persistEditingProps();
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
    }

    public function render(): View
    {
        return view('blade-page-builder::livewire.page-editor', [
            'sections' => $this->page->sections()->orderBy('order')->get(),
            'availableSections' => app(SectionRegistry::class)->slugs(),
            'selectedSchema' => $this->resolveSelectedSchema(),
            'previewHtml' => $this->renderPreview(),
            'revisions' => $this->page->revisions()->get(),
        ]);
    }

    private function resolveSelectedSchema(): ?Schema
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

        return app(SectionRegistry::class)->resolve($section->section_type)::schema();
    }

    private function renderPreview(): HtmlString
    {
        $sections = $this->page->sections()->orderBy('order')->get()
            ->map(fn (PageSection $section) => [
                'section_type' => $section->section_type,
                'props' => $section->props,
            ])
            ->all();

        return new HtmlString(app(SectionRenderer::class)->render($sections));
    }

    private function persistEditingProps(): void
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

        $schema = app(SectionRegistry::class)->resolve($section->section_type)::schema();

        $this->editingProps = $this->filterPropsAgainstSchema($this->editingProps, $schema);

        $section->update(['props' => $this->editingProps]);
    }

    /**
     * Livewire public properties are settable by the client for any nested key,
     * not just the ones rendered by the schema-driven form. Drop anything that
     * isn't a declared field, and coerce each declared field's value to the
     * shape its FieldType expects, before it reaches storage or the section
     * renderer — a declared scalar field (e.g. a Text field) can still be set
     * to an array via the same wire:model mechanism, which would otherwise
     * reach the section component constructor and throw a fatal TypeError.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function filterPropsAgainstSchema(array $props, Schema $schema): array
    {
        $filtered = [];

        foreach ($schema->fields() as $field) {
            if (! array_key_exists($field->name, $props)) {
                continue;
            }

            $value = $props[$field->name];
            $itemSchema = $field->itemSchema;

            $filtered[$field->name] = match (true) {
                $field->type === FieldType::Repeater && $itemSchema instanceof Schema => is_array($value)
                    ? array_values(array_map(
                        fn (mixed $item): array => $this->filterPropsAgainstSchema(is_array($item) ? $item : [], $itemSchema),
                        $value,
                    ))
                    : [],
                $field->type === FieldType::Group && $itemSchema instanceof Schema => $this->filterPropsAgainstSchema(is_array($value) ? $value : [], $itemSchema),
                $field->type === FieldType::Link => $this->filterLinkValue($value),
                default => $this->filterScalarValue($value),
            };
        }

        return $filtered;
    }

    /**
     * The Link field's Blade partial binds `label`, `url`, and `new_tab` as
     * separate sub-paths (resources/views/fields/link.blade.php), so its
     * value is a structured array rather than a plain scalar. Validate its
     * own sub-keys instead of coercing the whole value to a string.
     *
     * @return array{label: string, url: string, new_tab: bool}
     */
    private function filterLinkValue(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        $url = is_string($value['url'] ?? null) ? $value['url'] : '';

        return [
            'label' => is_string($value['label'] ?? null) ? $value['label'] : '',
            'url' => $this->isAllowedUrlScheme($url) ? $url : '',
            'new_tab' => is_scalar($value['new_tab'] ?? null) ? (bool) $value['new_tab'] : false,
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
     * Text, Textarea, Richtext, Image, and Select fields all bind to a single
     * scalar model path and are consumed as plain `string` section component
     * constructor parameters. Reject non-scalar values (arrays, objects) that
     * a client could otherwise inject via wire:model rather than passing them
     * through unchecked.
     */
    private function filterScalarValue(mixed $value): string
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
