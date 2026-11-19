<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use WeArePixel\Inlay\Actions\CreatePage;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Livewire\Concerns\InlayAdminComponent;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\ResolvedPageDefinition;
use WeArePixel\Inlay\Pages\RouteConflicts;
use WeArePixel\Inlay\Rules\PagePath;

final class PageCreate extends Component
{
    use InlayAdminComponent;

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
     * The route that answers this page's URL before Inlay can, so the page
     * would never show there.
     */
    #[Computed]
    public function routeConflict(): ?string
    {
        return app(RouteConflicts::class)->routeTaking($this->effectiveUrl());
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

        $page = app(CreatePage::class)->execute(
            definition: $definition,
            title: $this->title,
            path: $this->path,
            metaTitle: $this->metaTitle !== '' ? $this->metaTitle : null,
            metaDescription: $this->metaDescription !== '' ? $this->metaDescription : null,
            isHomepage: $this->isHomepage,
        );

        $this->redirect(app(InlayManager::class)->adminUrl('pages.edit', $page));
    }

    public function render(): View
    {
        return $this->adminView('inlay::livewire.page-create', __('inlay::admin.titles.create_page'));
    }
}
