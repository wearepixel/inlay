<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp;

use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Response;
use Throwable;
use WeArePixel\Inlay\Composition\CompositionReport;
use WeArePixel\Inlay\Contracts\Screenshotter;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionValidator;
use WeArePixel\Inlay\Pages\PageDefinitionViolation;
use WeArePixel\Inlay\Preview\PreviewLinks;
use WeArePixel\Inlay\Preview\PreviewRenderer;

/**
 * What an agent gets back after touching a page: what it renders, what
 * looks unfinished, whether it could be published, and where to look at it.
 */
final class PageFeedback
{
    public function __construct(
        private readonly CompositionReport $report,
        private readonly PreviewLinks $links,
        private readonly PreviewRenderer $preview,
        private readonly PageDefinitionValidator $validator,
    ) {}

    /**
     * @param  array<int, array{handle: string, settings?: array<string, mixed>}>|null  $proposed  null describes the stored draft
     * @return array<string, mixed>
     */
    public function describe(Page $page, ?array $proposed = null): array
    {
        $sections = $proposed ?? $this->preview->draftSections($page);
        $report = $this->report->build($sections);

        return array_filter([
            'sections' => $report['sections'],
            'warnings' => $report['warnings'],
            'publish_blockers' => $proposed === null ? array_map(
                fn (PageDefinitionViolation $violation) => $violation->message,
                $this->validator->validate($page),
            ) : null,
            'preview_url' => $this->previewUrl($page, $proposed),
            'preview_url_note' => $this->links->enabled()
                ? 'Signed link, valid for '.config('inlay.preview_links.ttl', 30).' minutes. Share it with a person to review the page.'
                : 'Preview links are off. A developer can enable them with Inlay::previewLinkRoutes().',
        ], fn (mixed $value) => $value !== null);
    }

    /**
     * @param  array<int, array{handle: string, settings?: array<string, mixed>}>|null  $proposed
     * @return array<int, Response>
     */
    public function screenshots(Page $page, ?array $proposed = null): array
    {
        if (! app()->bound(Screenshotter::class) || ! $this->links->enabled()) {
            return [];
        }

        $screenshotter = app(Screenshotter::class);
        $url = $this->previewUrl($page, $proposed);
        $responses = [];

        foreach ((array) config('inlay.mcp.screenshots.widths', ['desktop' => 1440, 'mobile' => 390]) as $label => $width) {
            try {
                $shot = $screenshotter->capture((string) $url, (int) $width);
            } catch (Throwable $exception) {
                Log::warning('inlay: preview screenshot failed', ['page_id' => $page->id, 'width' => $width, 'exception' => $exception->getMessage()]);
                $responses[] = Response::text("The {$label} screenshot failed: {$exception->getMessage()}");

                continue;
            }

            $responses[] = Response::text("{$label} screenshot ({$shot->width}px wide):");
            $responses[] = Response::image($shot->bytes, $shot->mimeType);
        }

        return $responses;
    }

    /**
     * @param  array<int, array{handle: string, settings?: array<string, mixed>}>|null  $proposed
     */
    private function previewUrl(Page $page, ?array $proposed): ?string
    {
        if (! $this->links->enabled()) {
            return null;
        }

        return $proposed === null ? $this->links->forPage($page) : $this->links->forComposition($page, $proposed);
    }
}
