<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Preview;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Support\SectionRenderer;

/**
 * Renders a composition inside the host's own preview view, so every
 * preview - the admin iframe, a shared link, a screenshot - shows the
 * page with the application's real frontend around it.
 */
final class PreviewRenderer
{
    public function __construct(
        private readonly SectionRenderer $renderer,
        private readonly InlayManager $inlay,
    ) {}

    /**
     * @param  array<int, array{handle: string, settings?: array<string, mixed>}>|null  $sections  null renders the page's stored draft
     */
    public function render(Page $page, ?array $sections = null): View
    {
        $composition = new HtmlString($this->renderer->render($sections ?? $this->draftSections($page)));

        $view = $this->inlay->resolvePreviewView();

        if ($view === null) {
            return view('inlay::preview-fallback', [
                'page' => $page,
                'composition' => $composition,
            ]);
        }

        return view($view, [
            'page' => $page,
            'composition' => $composition,
            'context' => null,
        ]);
    }

    /**
     * @return array<int, array{id: int, handle: string, settings: array<string, mixed>}>
     */
    public function draftSections(Page $page): array
    {
        return $page->sections()
            ->orderBy('order')
            ->get()
            ->map(fn (PageSection $section) => [
                'id' => $section->id,
                'handle' => $section->handle,
                'settings' => $section->settings ?? [],
            ])
            ->all();
    }
}
