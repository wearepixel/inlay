<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Response;
use Throwable;
use WeArePixel\Inlay\Composition\CompositionReport;
use WeArePixel\Inlay\Contracts\Screenshotter;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionValidator;
use WeArePixel\Inlay\Pages\PageDefinitionViolation;
use WeArePixel\Inlay\Preview\PreviewLinks;
use WeArePixel\Inlay\Preview\PreviewRenderer;
use WeArePixel\Inlay\Screenshots\Screenshot;

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
     * @param  string|null  $url  the signed preview link returned by describe()
     * @return array<int, Response>
     */
    public function screenshots(?Page $page, ?string $url): array
    {
        if ($url === null || ! app()->bound(Screenshotter::class)) {
            return [];
        }

        $key = 'inlay-mcp-screenshots:'.(auth()->id() ?? 'guest');

        if (! RateLimiter::attempt($key, max(1, (int) config('inlay.mcp.screenshots.per_minute', 6)), fn () => true, 60)) {
            return [Response::text('Screenshot limit reached - try again in '.RateLimiter::availableIn($key).' seconds. The report and preview link above are still current.')];
        }

        $screenshotter = app(Screenshotter::class);
        $responses = [];

        foreach ((array) config('inlay.mcp.screenshots.widths', ['desktop' => 1440, 'mobile' => 390]) as $label => $width) {
            try {
                $shot = $screenshotter->capture($url, (int) $width);
            } catch (Throwable $exception) {
                Log::warning('inlay: preview screenshot failed', ['page_id' => $page?->id, 'width' => $width, 'exception' => $exception->getMessage()]);
                $responses[] = Response::text("The {$label} screenshot failed. The error has been logged for a developer.");

                continue;
            }

            $responses[] = Response::text("{$label} screenshot ({$shot->width}px wide):");
            $responses[] = Response::image(...$this->limitHeight($shot));
        }

        return $responses;
    }

    /**
     * Very tall images are rejected or downscaled into illegibility by model
     * clients, so a long page is cut at the configured height.
     *
     * @return array{0: string, 1: string}
     */
    private function limitHeight(Screenshot $shot): array
    {
        $maxHeight = (int) config('inlay.mcp.screenshots.max_height', 7500);
        $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($shot->bytes) : false;

        if ($image === false || imagesy($image) <= $maxHeight) {
            return [$shot->bytes, $shot->mimeType];
        }

        $cropped = imagecrop($image, ['x' => 0, 'y' => 0, 'width' => imagesx($image), 'height' => $maxHeight]);

        if ($cropped === false) {
            return [$shot->bytes, $shot->mimeType];
        }

        ob_start();
        imagejpeg($cropped, null, 75);

        return [(string) ob_get_clean(), 'image/jpeg'];
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
