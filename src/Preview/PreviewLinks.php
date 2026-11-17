<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Preview;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use WeArePixel\Inlay\Models\Page;

/**
 * Issues signed, expiring URLs that render a page's draft - or a proposed
 * composition that was never saved - without an admin session. The
 * signature stops a link being forged or extended; the cached token is what
 * names the page and holds a proposal, and it expires with the link.
 *
 * Links are built from APP_URL, not the request's Host header: the screenshot
 * browser loads them, so a forged Host would point it at any server.
 */
final class PreviewLinks
{
    public const ROUTE = 'inlay.preview-link';

    private const CACHE_PREFIX = 'inlay:preview-link:';

    public function enabled(): bool
    {
        return Route::has(self::ROUTE);
    }

    public function forPage(Page $page): string
    {
        return $this->issue(['page_id' => $page->getKey(), 'sections' => null]);
    }

    /**
     * @param  array<int, array{handle: string, settings?: array<string, mixed>}>  $sections
     * @param  array<int, array<string, mixed>>  $draftSections  already-validated declarative section definitions the sections may use
     */
    public function forComposition(?Page $page, array $sections, array $draftSections = []): string
    {
        return $this->issue(['page_id' => $page?->getKey(), 'sections' => array_values($sections), 'section_types' => $draftSections]);
    }

    /**
     * @return array{page_id: int|null, sections: array<int, array{handle: string, settings?: array<string, mixed>}>|null, section_types?: array<int, array<string, mixed>>}|null
     */
    public function resolve(string $token): ?array
    {
        $entry = Cache::get(self::CACHE_PREFIX.$token);

        return is_array($entry) ? $entry : null;
    }

    /**
     * @param  array{page_id: mixed, sections: array<int, mixed>|null, sections?: array<int, mixed>}  $entry
     */
    private function issue(array $entry): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Preview links are not enabled. Call Inlay::previewLinkRoutes() from routes/web.php.');
        }

        $expiresAt = now()->addMinutes($this->ttl());
        $token = Str::random(40);

        Cache::put(self::CACHE_PREFIX.$token, $entry, $expiresAt);

        $relative = URL::temporarySignedRoute(self::ROUTE, $expiresAt, ['token' => $token], absolute: false);

        return rtrim((string) config('app.url'), '/').$relative;
    }

    private function ttl(): int
    {
        return max(1, (int) config('inlay.preview_links.ttl', 30));
    }
}
