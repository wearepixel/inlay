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
use WeArePixel\Inlay\Composition\BlockCatalogue;
use WeArePixel\Inlay\Composition\CompositionReport;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Declarative\DeclarativeBlockCompiler;
use WeArePixel\Inlay\Declarative\DeclarativeBlocks;
use WeArePixel\Inlay\Mcp\Apps\PreviewApp;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Mcp\PageFeedback;
use WeArePixel\Inlay\Preview\PreviewLinks;
use WeArePixel\Inlay\Support\BlockRegistry;

#[Name('inlay-save-block')]
#[Title('Create or update an Inlay block')]
#[RendersApp(PreviewApp::class)]
#[Description(<<<'TEXT'
Creates (or updates, by handle) a block you author yourself, for a design no existing block can
produce. Check inlay-list-blocks first - reuse an existing block whenever one fits. Use "dry_run": true
to preview without saving; iterate until the screenshots match the design, then save.

A block is {"handle", "name", "description", "category", "settings": [...], "template": {...}}.

settings - what editors can change: [{"name", "type", "label", "default", "required"?, "max_length"?,
"help"?, "options"? (select: [{"value","label"}]), "item_settings"? (repeater)}]. Types: text, textarea,
richtext, number, toggle, select, image, link, repeater. Put ALL visible copy, images and links in
settings (with the design's content as defaults) so editors can change them; literal text in the
template is for fixed labels only.

template - one root element node. A node is:
  {"tag", "class"?, "attrs"?, "children"? | "text"? | "html"?, "if"?, "each"?, "link"?}
  - tag: section div header footer article aside nav h1-h6 p span strong em small ul ol li dl dt dd a
    img figure figcaption blockquote details summary br hr
  - class: "space separated" or a list mixing strings and {"class": "...", "when": {"setting": "tone",
    "equals": "dark"}}. ONLY classes from inlay-get-styling; nothing else is accepted.
  - text: "literal" or {"setting": "heading"} (escaped). html: {"setting": "<richtext setting>"}.
  - attrs: id, role, title, aria-*; on img: src {"setting": "<image>"} and alt (required); on a: href.
  - link: {"setting": "<link setting>"} on an "a" node - sets href, target and its label; the node is
    omitted when the link is empty.
  - if: {"setting": "x"} (truthy / non-empty), with optional "equals" or "not_equals".
  - each: {"setting": "<repeater>", "as": "item"} repeats the node; inside it bind "item.<field>".
  - A text node is {"text": ...}. Inline styles, scripts, event handlers and other tags are rejected.

Invalid blocks are rejected with every problem and its path; unknown classes come with suggestions.
Returns what the block renders with its defaults, a signed preview link and, when enabled, screenshots.
TEXT)]
final class SaveBlockTool extends InlayTool
{
    public function handle(
        Request $request,
        DeclarativeBlockCompiler $compiler,
        DeclarativeBlocks $store,
        BlockRegistry $registry,
        CompositionReport $report,
        BlockCatalogue $catalogue,
        PreviewLinks $links,
        PageFeedback $feedback,
    ): Response|ResponseFactory {
        if ($denied = $this->deny($request) ?? $this->denyAuthoring($request)) {
            return $denied;
        }

        $definition = $request->get('block');

        if (! is_array($definition)) {
            return Response::error('"block" must be the block definition object.');
        }

        $dryRun = (bool) $request->get('dry_run', false);
        $handle = is_string($definition['handle'] ?? null) ? $definition['handle'] : '';

        if (! $dryRun && $store->exists($handle) && $store->usedByPublishedPage($handle) && ! $this->mayPublish($request)) {
            return Response::error("\"{$handle}\" is used by a published page, so changing it would change that live page without a publish. Save it under a new handle and swap it in on the draft, or ask someone who can publish.");
        }

        if ($throttled = $this->throttle($request)) {
            return $throttled;
        }

        try {
            $compiled = $dryRun
                ? $compiler->compile($definition)
                : $store->save($definition, $this->userId($request));
        } catch (InvalidCompositionException $exception) {
            return $this->invalid($exception);
        }

        $section = ['handle' => $compiled->handle, 'settings' => []];
        $url = $links->enabled() ? $links->forComposition(null, [$section], [$definition]) : null;

        $registry->registerDraft($compiled);

        try {
            $renders = $report->build([$section])['sections'][0] ?? null;
        } finally {
            $registry->forgetDeclarative();
        }

        $described = [
            'saved' => ! $dryRun,
            'block' => $catalogue->describeBlock($compiled),
            'renders' => $renders,
            'preview_url' => $url,
            'next' => $dryRun
                ? 'Compare the preview with the design, adjust, then call again without dry_run to save.'
                : 'The block is available now: add it to a page with inlay-replace-sections.',
        ];

        return Response::make([
            Response::text((string) json_encode($described, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            ...$feedback->screenshots(null, $url),
        ])->withStructuredContent($described);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'block' => $schema->object()->description('The block definition. See the tool description for the format.')->required(),
            'dry_run' => $schema->boolean()->description('Preview without saving.'),
        ];
    }

    private function mayPublish(Request $request): bool
    {
        return (bool) config('inlay.mcp.allow_publish', false)
            && $this->allows($request, (string) config('inlay.mcp.abilities.publish', 'inlay.publish-pages'));
    }

    private function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }
}
