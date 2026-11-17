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
use WeArePixel\Inlay\Composition\SectionCatalogue;
use WeArePixel\Inlay\Mcp\InlayTool;

#[Name('inlay-list-sections')]
#[Title('List Inlay sections')]
#[IsReadOnly]
#[Description(<<<'TEXT'
Lists the sections Inlay pages are composed from, with every setting each section accepts: its type,
the exact value format, constraints, options and default.

Call this before composing or editing a page. Pass page_id to see only the sections that page's
type allows, and which of them are required or locked on it.

Sections are fixed, developer-built components. When a design needs a section no section can
produce, say so and name the gap rather than forcing the closest section into the wrong shape.
TEXT)]
final class ListSectionsTool extends InlayTool
{
    public function handle(Request $request, SectionCatalogue $catalogue): Response|ResponseFactory
    {
        if ($request->get('page_id') === null) {
            return $this->deny($request) ?? Response::structured(['sections' => $catalogue->describe()]);
        }

        $page = $this->authorizedPage($request);

        if ($page instanceof Response) {
            return $page;
        }

        $definition = $this->definitionFor($page);

        if ($definition === null) {
            return Response::error("This page's type is no longer registered, so no sections can be added to it.");
        }

        return Response::structured([
            'page_type' => $definition->label,
            'sections' => $catalogue->describe($definition),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('Only list sections this page may contain.'),
        ];
    }
}
