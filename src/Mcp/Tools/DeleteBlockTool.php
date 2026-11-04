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
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Declarative\DeclarativeBlocks;
use WeArePixel\Inlay\Mcp\InlayTool;

#[Name('inlay-delete-block')]
#[Title('Delete an agent-authored block')]
#[IsDestructive]
#[Description('Deletes a block created with inlay-save-block. Refused while any page section still uses it.')]
final class DeleteBlockTool extends InlayTool
{
    public function handle(Request $request, DeclarativeBlocks $store): Response|ResponseFactory
    {
        if ($denied = $this->deny($request) ?? $this->denyAuthoring($request)) {
            return $denied;
        }

        if ($throttled = $this->throttle($request)) {
            return $throttled;
        }

        try {
            $store->delete((string) $request->get('handle'));
        } catch (InvalidCompositionException $exception) {
            return $this->invalid($exception);
        }

        return Response::structured(['deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->required(),
        ];
    }
}
