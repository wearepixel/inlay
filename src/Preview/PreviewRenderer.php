<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Preview;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Support\SectionRegistry;
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
        private readonly SectionRegistry $sections,
    ) {}

    /**
     * @param  array<int, array{handle: string, settings?: array<string, mixed>}>|null  $sections  null renders the page's stored draft
     */
    public function render(Page $page, ?array $sections = null, bool $selectable = false): View
    {
        $sections ??= $this->draftSections($page);

        $composition = new HtmlString($selectable
            ? $this->renderSelectable($sections)
            : $this->renderer->render($sections));

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
     * The admin editor's preview: each section is tagged with its id so a
     * click in the preview can select it, and the script that draws the
     * hover and selection outlines is appended.
     *
     * @param  array<int, array{id?: int, handle: string, settings?: array<string, mixed>}>  $sections
     */
    private function renderSelectable(array $sections): string
    {
        $html = '';

        foreach ($sections as $section) {
            $rendered = $this->renderer->render([$section]);

            if (! isset($section['id']) || $rendered === '') {
                $html .= $rendered;

                continue;
            }

            $label = $this->sections->hasHandle($section['handle'])
                ? $this->sections->definition($section['handle'])->name
                : $section['handle'];

            $html .= sprintf(
                '<div data-inlay-section="%d" data-inlay-section-label="%s" style="display: contents">%s</div>',
                $section['id'],
                e($label),
                $rendered,
            );
        }

        $script = (string) file_get_contents(__DIR__.'/../../resources/dist/inlay-preview.js');

        return $html.'<script>'.$script.'</script>';
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
