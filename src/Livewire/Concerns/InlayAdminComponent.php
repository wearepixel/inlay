<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Livewire\Concerns;

use Illuminate\Contracts\View\View;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;

trait InlayAdminComponent
{
    /**
     * "booted" runs after mount() on the first request and after hydrate()
     * on every Livewire action, so this also covers a component the host
     * mounted on a route whose middleware Livewire does not re-run.
     */
    public function bootedInlayAdminComponent(): void
    {
        app(InlayManager::class)->authorizeAdmin($this->authorizedPage());
    }

    protected function authorizedPage(): ?Page
    {
        return null;
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function adminView(string $view, string $title, array $data = []): View
    {
        $layout = config('inlay.admin.layout');

        $rendered = view($view, ['title' => $title, ...$data]);

        if (! is_string($layout) || $layout === '') {
            return $rendered->layout('inlay::layout', ['title' => $title]);
        }

        return $rendered->layout('inlay::host-layout', ['component' => $layout, 'title' => $title]);
    }
}
