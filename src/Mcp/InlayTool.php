<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use WeArePixel\Inlay\Composition\CompositionError;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Declarative\DeclarativeSections;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\ResolvedPageDefinition;

/**
 * Inlay's tools may be mounted inside a host's own MCP server, which has its
 * own audience, so each tool authorises the request itself rather than
 * trusting the route it arrived on.
 */
abstract class InlayTool extends Tool
{
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    protected function deny(Request $request, ?Page $page = null): ?Response
    {
        $ability = (string) config('inlay.mcp.abilities.manage', 'inlay.manage-pages');

        if ($this->allows($request, $ability, $page)) {
            return null;
        }

        return Response::error("You are not allowed to manage Inlay pages. This requires the \"{$ability}\" ability - ask a developer to grant it.");
    }

    /**
     * Creating sections changes what every editor can put on a page, so it needs
     * its own ability and is off unless the host enables it.
     */
    protected function denyAuthoring(Request $request): ?Response
    {
        if (! DeclarativeSections::enabled()) {
            return Response::error('Creating sections over MCP is switched off for this site. Compose pages from the existing sections, or ask a developer to set inlay.agent_sections.enabled.');
        }

        $ability = (string) config('inlay.mcp.abilities.author', 'inlay.author-sections');

        if (! $this->allows($request, $ability)) {
            return Response::error("You are not allowed to create Inlay sections. This requires the \"{$ability}\" ability.");
        }

        return null;
    }

    protected function allows(Request $request, string $ability, ?Page $page = null): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $page === null ? [] : [$page]);
    }

    protected function throttle(Request $request): ?Response
    {
        $key = 'inlay-mcp-writes:'.$request->user()?->getAuthIdentifier();
        $limit = max(1, (int) config('inlay.mcp.writes_per_minute', 60));

        if (RateLimiter::attempt($key, $limit, fn () => true, self::RATE_LIMIT_WINDOW_SECONDS)) {
            return null;
        }

        return Response::error('Inlay write limit reached. Try again in '.RateLimiter::availableIn($key).' seconds.');
    }

    /**
     * Resolves the page named by `page_id` and authorises against that page
     * only, so a host gate may require the Page argument. A page the user may
     * not manage is reported exactly like one that does not exist.
     *
     * @return Page|Response the page, or the error to return
     */
    protected function authorizedPage(Request $request): Page|Response
    {
        $pageId = $request->get('page_id');
        $page = is_numeric($pageId) ? Page::query()->find((int) $pageId) : null;
        $ability = (string) config('inlay.mcp.abilities.manage', 'inlay.manage-pages');

        if ($page === null || ! $this->allows($request, $ability, $page)) {
            return Response::error('No page you can manage has page_id '.(is_scalar($pageId) ? $pageId : '?').'. Call inlay-list-pages for valid ids.');
        }

        return $page;
    }

    protected function invalid(InvalidCompositionException $exception, string $hint = ''): Response
    {
        return Response::error((string) json_encode([
            'error' => 'Nothing was saved. Fix every problem below and try again.'.($hint !== '' ? ' '.$hint : ''),
            'problems' => array_map(fn (CompositionError $error) => $error->toArray(), $exception->errors),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function definitionFor(Page $page): ?ResolvedPageDefinition
    {
        return app(PageDefinitionRegistry::class)->resolveForPage($page);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pageSummary(Page $page): array
    {
        $latestRevision = $page->revisions()->first();

        return [
            'id' => $page->id,
            'title' => $page->title,
            'path' => $page->path,
            'public_url' => app(InlayManager::class)->url($page),
            'status' => $page->status->value,
            'definition' => $this->definitionFor($page)->handle ?? $page->definition,
            'has_unpublished_changes' => $latestRevision === null || $latestRevision->created_at->lt($page->updated_at),
            'updated_at' => $page->updated_at->toIso8601String(),
        ];
    }
}
