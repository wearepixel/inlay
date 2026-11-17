<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use WeArePixel\Inlay\Actions\ReplacePageComposition;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Mcp\Apps\PreviewApp;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Mcp\PageFeedback;

#[Name('inlay-preview-page')]
#[Title('Preview an Inlay page')]
#[IsReadOnly]
#[RendersApp(PreviewApp::class)]
#[Description(<<<'TEXT'
Shows what a page will look like, without saving anything.

Without "sections" it previews the page's current draft. With "sections" (same format as
inlay-replace-sections) it previews that proposed composition instead - use this to try a layout
before committing to it.

Returns, for every section: its headings, links, images and visible text as rendered, the content
still coming from section defaults (usually placeholder copy you should replace), and problems such as
missing alt text, empty links or a section that failed to render. Also returns a signed preview link,
and desktop and mobile screenshots when the site has screenshots enabled.

When matching a design (e.g. a Figma frame), compare the screenshots against it and iterate.
Differences in spacing, colour or typography usually come from the section's own styling, which you
cannot change - report them rather than trying to work around them with content.
TEXT)]
final class PreviewPageTool extends InlayTool
{
    public function handle(Request $request, ReplacePageComposition $replace, PageFeedback $feedback): Response|ResponseFactory
    {
        $page = $this->authorizedPage($request);

        if ($page instanceof Response) {
            return $page;
        }

        $proposed = null;
        $sections = $request->get('sections');

        if ($sections !== null) {
            if (! is_array($sections)) {
                return Response::error('"sections" must be a list of {"section": ..., "settings": {...}} objects.');
            }

            $errors = $replace->validate($page, $sections);

            if ($errors !== []) {
                return $this->invalid(new InvalidCompositionException($errors), 'Nothing was previewed.');
            }

            $proposed = array_map(fn (array $section) => [
                'handle' => $section['section'],
                'settings' => $replace->storable($section['section'], $section['settings'] ?? []),
            ], $sections);
        }

        if ($throttled = $this->throttle($request)) {
            return $throttled;
        }

        $described = [
            'page' => $this->pageSummary($page),
            'previewing' => $proposed === null ? 'saved draft' : 'proposed sections (not saved)',
            ...$feedback->describe($page, $proposed),
        ];

        return Response::make([
            Response::text((string) json_encode($described, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            ...$feedback->screenshots($page, $described['preview_url'] ?? null),
        ])->withStructuredContent($described);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->required(),
            'sections' => SectionsSchema::make($schema)->description('Optional proposed composition to preview instead of the saved draft.'),
        ];
    }
}
