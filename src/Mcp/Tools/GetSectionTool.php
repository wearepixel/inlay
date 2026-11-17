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
use WeArePixel\Inlay\Declarative\DeclarativeSections;
use WeArePixel\Inlay\Mcp\InlayTool;

#[Name('inlay-get-section')]
#[Title('Get an agent-authored section')]
#[IsReadOnly]
#[Description('Returns the full definition (settings and template) of a section created with inlay-save-section, so you can change it and save it again. Developer-built sections cannot be edited this way.')]
final class GetSectionTool extends InlayTool
{
    public function handle(Request $request, DeclarativeSections $store): Response|ResponseFactory
    {
        if ($denied = $this->deny($request) ?? $this->denyAuthoring($request)) {
            return $denied;
        }

        $source = $store->source((string) $request->get('handle'));

        if ($source === null) {
            return Response::error('No agent-authored section has that handle. Developer-built sections are listed by inlay-list-sections but cannot be edited here.');
        }

        return Response::structured(['section' => $source]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->required(),
        ];
    }
}
