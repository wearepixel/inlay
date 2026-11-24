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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Pages\PageDefinitionViolation;
use WeArePixel\Inlay\Pages\PageDefinitionViolationException;

#[Name('inlay-publish-page')]
#[Title('Publish an Inlay page')]
#[IsDestructive]
#[IsOpenWorld]
#[Description(<<<'TEXT'
Publishes a page's current draft, making it PUBLIC immediately. Only call this when the person you
are working with has explicitly asked to publish, after they have reviewed the preview. Earlier
versions stay in the page's history and can be restored in the admin.
TEXT)]
final class PublishPageTool extends InlayTool
{
    public function handle(Request $request, PublishPage $publish): Response|ResponseFactory
    {
        $page = $this->authorizedPage($request);

        if ($page instanceof Response) {
            return $page;
        }

        if (! config('inlay.mcp.allow_publish', false)) {
            return Response::error('Publishing over MCP is switched off for this site. Ask a person to publish it from the admin, or a developer to set inlay.mcp.allow_publish.');
        }

        $ability = (string) config('inlay.mcp.abilities.publish', 'inlay.publish-pages');

        if (! $this->allows($request, $ability, $page)) {
            return Response::error("You are not allowed to publish Inlay pages. This requires the \"{$ability}\" ability.");
        }

        if ($throttled = $this->throttle($request)) {
            return $throttled;
        }

        try {
            $revision = $publish->execute($page, $this->userId($request), 'Published via MCP');
        } catch (PageDefinitionViolationException $exception) {
            return Response::error('The page was not published: '.implode(' ', array_map(
                fn (PageDefinitionViolation $violation) => $violation->message,
                $exception->violations,
            )));
        }

        return Response::structured([
            'published' => true,
            'revision_id' => $revision->id,
            'page' => $this->pageSummary($page->refresh()),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->required(),
        ];
    }
}
