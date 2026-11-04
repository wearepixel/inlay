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
use WeArePixel\Inlay\Declarative\DeclarativeBlocks;
use WeArePixel\Inlay\Mcp\InlayTool;

#[Name('inlay-get-block')]
#[Title('Get an agent-authored block')]
#[IsReadOnly]
#[Description('Returns the full definition (settings and template) of a block created with inlay-save-block, so you can change it and save it again. Developer-built blocks cannot be edited this way.')]
final class GetBlockTool extends InlayTool
{
    public function handle(Request $request, DeclarativeBlocks $store): Response|ResponseFactory
    {
        if ($denied = $this->deny($request) ?? $this->denyAuthoring($request)) {
            return $denied;
        }

        $source = $store->source((string) $request->get('handle'));

        if ($source === null) {
            return Response::error('No agent-authored block has that handle. Developer-built blocks are listed by inlay-list-blocks but cannot be edited here.');
        }

        return Response::structured(['block' => $source]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->required(),
        ];
    }
}
