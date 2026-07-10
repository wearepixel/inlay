<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Models\PageSection;
use WearePixel\BladePageBuilder\Schema\FieldType;
use WearePixel\BladePageBuilder\Schema\Schema;
use WearePixel\BladePageBuilder\Support\SectionRegistry;
use WearePixel\BladePageBuilder\Support\SectionRenderer;

final class PageEditor extends Component
{
    public Page $page;

    public ?int $selectedSectionId = null;

    /** @var array<string, mixed> */
    public array $editingProps = [];

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
            $props[$field->name] = $field->type === FieldType::Repeater ? [] : '';
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

        $this->selectedSectionId = $sectionId;
        $this->editingProps = $section->props ?? [];
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

    public function publish(): void
    {
        app(PublishPage::class)->execute($this->page, auth()->id());
        $this->dispatch('page-published');
    }

    public function render(): View
    {
        return view('blade-page-builder::livewire.page-editor', [
            'sections' => $this->page->sections()->orderBy('order')->get(),
            'availableSections' => app(SectionRegistry::class)->slugs(),
            'selectedSchema' => $this->resolveSelectedSchema(),
            'previewHtml' => $this->renderPreview(),
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

        PageSection::whereKey($this->selectedSectionId)
            ->where('page_id', $this->page->id)
            ->update(['props' => $this->editingProps]);
    }
}
