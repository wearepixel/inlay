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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use WeArePixel\Inlay\Actions\ReplacePageComposition;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Mcp\PageFeedback;

#[Name('inlay-replace-sections')]
#[Title('Replace an Inlay page\'s sections')]
#[IsIdempotent]
#[Description(<<<'TEXT'
Replaces a page's whole DRAFT composition with the sections given, in order. The published page
does not change until it is published.

Each section is {"section": "<handle>", "settings": {...}}. Settings use the formats from
inlay-list-sections; omit a setting to use its default. Images must be uploaded first with
inlay-upload-image.

All or nothing: if any section is invalid nothing is saved, and every problem is returned with its
path (e.g. sections.2.settings.cta.url) so you can fix them all in one retry. Locked sections must
stay at their current index and required sections must be present.

To try a layout without saving it, use inlay-preview-page with "sections" instead.
Returns a report of what each section renders, publish blockers and a signed preview link.
TEXT)]
final class ReplaceSectionsTool extends InlayTool
{
    public function handle(Request $request, ReplacePageComposition $replace, PageFeedback $feedback): Response|ResponseFactory
    {
        $page = $this->authorizedPage($request);

        if ($page instanceof Response) {
            return $page;
        }

        $sections = $request->get('sections');

        if (! is_array($sections)) {
            return Response::error('"sections" must be a list of {"section": ..., "settings": {...}} objects.');
        }

        if ($throttled = $this->throttle($request)) {
            return $throttled;
        }

        try {
            $replace->execute($page, $sections);
        } catch (InvalidCompositionException $exception) {
            return $this->invalid($exception);
        }

        return Response::structured([
            'saved' => true,
            'page' => $this->pageSummary($page->refresh()),
            'feedback' => $feedback->describe($page),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->required(),
            'sections' => SectionsSchema::make($schema)->description('The complete new composition, top to bottom.')->required(),
        ];
    }
}
