<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use WearePixel\Inlay\InlayManager;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Models\PageSection;
use WearePixel\Inlay\Support\SectionRenderer;

/**
 * Renders a page's current draft composition through the same Blade
 * renderer used for public rendering, then hands it to the host
 * application's own configured view (Inlay::previewView()) so the admin's
 * preview iframe loads the host's real frontend - never a recreation of it
 * inside the admin - docs/v1-implementation-specification.md §18.
 */
final class PagePreviewController
{
    public function __construct(private readonly SectionRenderer $renderer) {}

    public function __invoke(Page $page, InlayManager $inlay): View
    {
        $sections = $page->sections()
            ->orderBy('order')
            ->get()
            ->map(fn (PageSection $section) => [
                'handle' => $section->handle,
                'settings' => $section->settings ?? [],
            ])
            ->all();

        $composition = new HtmlString($this->renderer->render($sections));

        $view = $inlay->resolvePreviewView();

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
}
