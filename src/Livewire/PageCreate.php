<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use WeArePixel\Inlay\Actions\SetPageAsHomepage;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\ResolvedPageDefinition;
use WeArePixel\Inlay\Rules\PagePath;

#[Layout('inlay::layout')]
final class PageCreate extends Component
{
    public string $title = '';

    public string $path = '';

    public string $metaTitle = '';

    public string $metaDescription = '';

    public bool $isHomepage = false;

    public string $definitionHandle = PageDefinitionRegistry::DEFAULT_HANDLE;

    /**
     * The URL this page will resolve to once published, shown live beneath
     * the path field so an editor sees the effect of the active
     * Inlay::pageRoutes() prefix without it ever being stored on the Page
     * itself (docs/inlay/v1/routing).
     */
    #[Computed]
    public function effectiveUrl(): ?string
    {
        $page = new Page([
            'path' => Page::normalizePath($this->path),
            'is_homepage' => $this->isHomepage,
        ]);

        return app(InlayManager::class)->url($page);
    }

    /**
     * Every registered Page Definition, for the "Page type" selector - the
     * view hides the selector entirely when only the built-in unrestricted
     * one exists, so a developer who never needs this feature sees no
     * extra ceremony at all (docs/inlay/v1/page-definitions#page-creation).
     *
     * @return array<int, ResolvedPageDefinition>
     */
    #[Computed]
    public function definitions(): array
    {
        return app(PageDefinitionRegistry::class)->all();
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255', new PagePath],
            'metaTitle' => ['nullable', 'string', 'max:255'],
            'metaDescription' => ['nullable', 'string', 'max:2000'],
        ]);

        $registry = app(PageDefinitionRegistry::class);
        $definition = $registry->definition($this->definitionHandle) ?? $registry->default();

        $page = Page::create([
            'title' => $this->title,
            'path' => $this->path,
            'meta_title' => $this->metaTitle !== '' ? $this->metaTitle : null,
            'meta_description' => $this->metaDescription !== '' ? $this->metaDescription : null,
            // Deliberately fixed at creation - Page Definition is
            // immutable afterward for this release, a decisive
            // simplification the spec explicitly sanctions rather than
            // building a "validate and confirm every existing block
            // against the new rules" migration flow
            // (docs/inlay/v1/page-definitions#page-creation).
            'definition' => $definition->handle,
        ]);

        // Seeded in the definition's own declared order, with sparse
        // settings resolved from each block's current defaults - identical
        // in shape to adding one by hand from the picker
        // (docs/v1-implementation-specification.md §12).
        foreach ($definition->defaultBlocks as $index => $handle) {
            $page->sections()->create([
                'handle' => $handle,
                'order' => $index,
                'settings' => [],
            ]);
        }

        if ($this->isHomepage) {
            app(SetPageAsHomepage::class)->execute($page);
        }

        $this->redirectRoute('inlay.admin.pages.edit', ['page' => $page], navigate: false);
    }

    public function render(): View
    {
        return view('inlay::livewire.page-create', [
            'title' => 'Create page · Inlay',
        ]);
    }
}
