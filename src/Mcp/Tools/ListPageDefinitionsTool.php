<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\ResolvedPageDefinition;

#[Name('inlay-list-page-types')]
#[Title('List Inlay page types')]
#[IsReadOnly]
#[Description(<<<'TEXT'
Lists the page types (Page Definitions) a new page can be created as. A type decides which blocks
a page may contain, which are required, which are locked in place, and which blocks a new page
starts with. A page's type is fixed when it is created - pick it with inlay-create-page.
TEXT)]
final class ListPageDefinitionsTool extends InlayTool
{
    public function handle(Request $request, PageDefinitionRegistry $definitions): Response|ResponseFactory
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        return Response::structured([
            'page_types' => array_values(array_map(fn (ResolvedPageDefinition $definition) => [
                'handle' => $definition->handle,
                'label' => $definition->label,
                'description' => $definition->description,
                'allowed_blocks' => $definition->allowedBlocks ?? 'any',
                'required_blocks' => $definition->requiredBlocks,
                'locked_blocks' => $definition->lockedBlocks,
                'starts_with' => $definition->defaultBlocks,
            ], $definitions->all())),
        ]);
    }
}
