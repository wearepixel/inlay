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
use WeArePixel\Inlay\Declarative\DeclarativeSections;
use WeArePixel\Inlay\Mcp\InlayTool;

#[Name('inlay-delete-section')]
#[Title('Delete an agent-authored section')]
#[IsDestructive]
#[Description('Deletes a section created with inlay-save-section. Refused while any page section still uses it.')]
final class DeleteSectionTool extends InlayTool
{
    public function handle(Request $request, DeclarativeSections $store): Response|ResponseFactory
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
