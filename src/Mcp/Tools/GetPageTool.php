<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;

#[Name('inlay-get-page')]
#[Title('Get an Inlay page')]
#[IsReadOnly]
#[Description(<<<'TEXT'
Returns a page's details and its current draft sections in order. Each section has its id, its
section, the settings explicitly set on it ("settings") and the values it actually renders with,
defaults included ("effective_settings").

To change a page, send the complete new section list to inlay-replace-sections, or change one
section with inlay-update-section.
TEXT)]
final class GetPageTool extends InlayTool
{
    public function handle(Request $request, SectionRegistry $registry, SectionRenderer $renderer): Response|ResponseFactory
    {
        $page = $this->authorizedPage($request);

        if ($page instanceof Response) {
            return $page;
        }

        $definition = $this->definitionFor($page);

        $sections = $page->sections()->orderBy('order')->get()->map(function (PageSection $section) use ($registry, $renderer) {
            $registered = $registry->hasHandle($section->handle);

            return array_filter([
                'id' => $section->id,
                'section' => $section->handle,
                'name' => $registered ? $registry->definition($section->handle)->name : null,
                'settings' => $section->settings ?? [],
                'effective_settings' => $registered
                    ? $renderer->resolveEffectiveRawSettings($registry->definition($section->handle), $section->settings ?? [])
                    : null,
                'problem' => $registered ? null : 'This section is no longer registered and does not render.',
            ], fn (mixed $value) => $value !== null);
        })->all();

        return Response::structured([
            ...$this->pageSummary($page),
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'page_type' => $definition === null ? null : [
                'label' => $definition->label,
                'required_sections' => $definition->requiredSections,
                'locked_sections' => $definition->lockedSections,
            ],
            'admin_url' => Route::has('inlay.admin.pages.edit') ? route('inlay.admin.pages.edit', $page) : null,
            'sections' => $sections,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->required(),
        ];
    }
}
