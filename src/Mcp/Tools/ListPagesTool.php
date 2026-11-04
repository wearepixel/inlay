<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Models\Page;

#[Name('inlay-list-pages')]
#[Title('List Inlay pages')]
#[IsReadOnly]
#[Description('Lists Inlay pages, most recently updated first. Filter by a title or path search. Check here before creating a page so you do not duplicate one.')]
final class ListPagesTool extends InlayTool
{
    private const PER_PAGE = 50;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        $search = trim((string) $request->get('search', ''));
        $page = max(1, (int) $request->get('page', 1));

        $query = Page::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('path', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest('updated_at')
            ->latest('id');

        $results = $query->forPage($page, self::PER_PAGE + 1)->get();
        $hasMore = $results->count() > self::PER_PAGE;

        $pages = $results->take(self::PER_PAGE)
            ->filter(fn (Page $candidate) => $this->deny($request, $candidate) === null)
            ->map(fn (Page $candidate) => $this->pageSummary($candidate))
            ->values()
            ->all();

        return Response::structured([
            'pages' => $pages,
            'page' => $page,
            'has_more' => $hasMore,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Matches part of a title or path.'),
            'page' => $schema->integer()->description('Results page, starting at 1.'),
        ];
    }
}
