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
use WeArePixel\Inlay\Actions\UpdatePageSection;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Mcp\PageFeedback;

#[Name('inlay-update-section')]
#[Title('Update one Inlay section')]
#[Description(<<<'TEXT'
Changes some settings of one section of a page's DRAFT, leaving every other setting and section as
it is. Send only the settings to change; send null for a setting to reset it to the section default.
Get section ids from inlay-get-page. To add, remove or reorder sections use inlay-replace-sections.
TEXT)]
final class UpdateSectionTool extends InlayTool
{
    public function handle(Request $request, UpdatePageSection $update, PageFeedback $feedback): Response|ResponseFactory
    {
        $page = $this->authorizedPage($request);

        if ($page instanceof Response) {
            return $page;
        }

        $settings = $request->get('settings');

        if (! is_array($settings) || ($settings !== [] && array_is_list($settings))) {
            return Response::error('"settings" must be an object keyed by setting name.');
        }

        if ($throttled = $this->throttle($request)) {
            return $throttled;
        }

        try {
            $update->execute($page, (int) $request->get('section_id'), $settings);
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
            'section_id' => $schema->integer()->required(),
            'settings' => $schema->object()->description('Settings to change, keyed by setting name.')->required(),
        ];
    }
}
