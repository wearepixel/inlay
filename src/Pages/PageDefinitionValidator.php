<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Support\SectionRegistry;

/**
 * The single authoritative check for whether a Page currently satisfies its
 * own Page Definition - used identically by the editor's own status
 * display, PublishPage's publish gate, and tests
 * (docs/inlay/v1/page-definitions#publishing-validation). An empty return means
 * the Page is valid; publishing must be blocked whenever this returns
 * anything.
 */
final class PageDefinitionValidator
{
    public function __construct(
        private readonly PageDefinitionRegistry $definitions,
        private readonly SectionRegistry $sections,
    ) {}

    /**
     * @return array<int, PageDefinitionViolation>
     */
    public function validate(Page $page): array
    {
        $definition = $this->definitions->resolveForPage($page);

        if ($definition === null) {
            return [
                new PageDefinitionViolation(
                    type: 'unresolved_definition',
                    message: 'This page\'s type is no longer registered. A developer needs to resolve this before it can be published.',
                ),
            ];
        }

        $violations = [];
        $presentHandles = [];

        foreach ($page->sections()->orderBy('order')->get() as $section) {
            /** @var PageSection $section */
            $presentHandles[] = $section->handle;

            if (! $this->sections->hasHandle($section->handle)) {
                $violations[] = new PageDefinitionViolation(
                    type: 'unresolved_section',
                    message: 'This page contains a section that is no longer registered. A developer needs to resolve this before it can be published.',
                    sectionHandle: $section->handle,
                );

                continue;
            }

            if (! $definition->allows($section->handle)) {
                $violations[] = new PageDefinitionViolation(
                    type: 'disallowed_section',
                    message: "{$this->sections->definition($section->handle)->name} is not allowed on {$definition->label}.",
                    sectionHandle: $section->handle,
                );
            }
        }

        foreach ($definition->requiredSections as $requiredHandle) {
            if (in_array($requiredHandle, $presentHandles, true)) {
                continue;
            }

            $label = $this->sections->hasHandle($requiredHandle)
                ? $this->sections->definition($requiredHandle)->name
                : $requiredHandle;

            $violations[] = new PageDefinitionViolation(
                type: 'missing_required_section',
                message: "{$label} is required by {$definition->label}.",
                sectionHandle: $requiredHandle,
            );
        }

        return $violations;
    }
}
