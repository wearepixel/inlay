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
use WeArePixel\Inlay\Rules\PagePath;

#[Layout('inlay::layout')]
final class PageCreate extends Component
{
    public string $title = '';

    public string $path = '';

    public string $metaTitle = '';

    public string $metaDescription = '';

    public bool $isHomepage = false;

    /**
     * The URL this page will resolve to once published, shown live beneath
     * the path field so an editor sees the effect of the active
     * Inlay::pageRoutes() prefix without it ever being stored on the Page
     * itself (docs/routing.md).
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

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255', new PagePath],
            'metaTitle' => ['nullable', 'string', 'max:255'],
            'metaDescription' => ['nullable', 'string', 'max:2000'],
        ]);

        $page = Page::create([
            'title' => $this->title,
            'path' => $this->path,
            'meta_title' => $this->metaTitle !== '' ? $this->metaTitle : null,
            'meta_description' => $this->metaDescription !== '' ? $this->metaDescription : null,
        ]);

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
