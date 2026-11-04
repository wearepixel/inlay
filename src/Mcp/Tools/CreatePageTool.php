<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use WeArePixel\Inlay\Actions\CreatePage;
use WeArePixel\Inlay\Actions\ReplacePageComposition;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Mcp\PageFeedback;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Rules\PagePath;

#[Name('inlay-create-page')]
#[Title('Create an Inlay page')]
#[Description(<<<'TEXT'
Creates a new DRAFT page. Nothing is public until it is published.

BEFORE CALLING: check inlay-list-pages so you do not duplicate a page, and inlay-list-page-types
to choose its type (fixed once created; omit it for an unrestricted page).

Without "sections" the page starts with its type's default blocks. With "sections" (same format as
inlay-replace-sections) the page is created with exactly that composition, and nothing is created
at all if any section is invalid.

Returns the page id, a report of what each section renders and a signed preview link.
TEXT)]
final class CreatePageTool extends InlayTool
{
    public function handle(Request $request, CreatePage $createPage, ReplacePageComposition $replace, PageDefinitionRegistry $definitions, PageFeedback $feedback): Response|ResponseFactory
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255', new PagePath],
            'page_type' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'sections' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return Response::error(implode(' ', $validator->errors()->all()));
        }

        $input = $validator->validated();
        $definition = isset($input['page_type']) ? $definitions->definition($input['page_type']) : $definitions->default();

        if ($definition === null) {
            return Response::error("Unknown page_type \"{$input['page_type']}\". Call inlay-list-page-types for the valid handles.");
        }

        if ($throttled = $this->throttle($request)) {
            return $throttled;
        }

        try {
            $page = DB::transaction(function () use ($createPage, $replace, $definition, $input) {
                $page = $createPage->execute(
                    definition: $definition,
                    title: $input['title'],
                    path: $input['path'] ?? null,
                    metaTitle: $input['meta_title'] ?? null,
                    metaDescription: $input['meta_description'] ?? null,
                );

                if (isset($input['sections'])) {
                    $replace->execute($page, $input['sections']);
                }

                return $page;
            });
        } catch (InvalidCompositionException $exception) {
            return $this->invalid($exception, 'The page was not created.');
        }

        return Response::structured([
            'created' => $this->pageSummary($page->refresh()),
            'feedback' => $feedback->describe($page),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Internal title, also the default meta title.')->required(),
            'path' => $schema->string()->description('Public path, e.g. "offers/spring". No domain or query string.'),
            'page_type' => $schema->string()->description('Page type handle from inlay-list-page-types.'),
            'meta_title' => $schema->string(),
            'meta_description' => $schema->string(),
            'sections' => SectionsSchema::make($schema)->description('Optional initial composition. Same format as inlay-replace-sections.'),
        ];
    }
}
