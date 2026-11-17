<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

use WeArePixel\Inlay\Contracts\InlaySection;
use WeArePixel\Inlay\Support\SectionHandle;

/**
 * Developer-owned composition *policy* for a kind of Page - which Sections it
 * may contain, which are required or locked, and what a new Page of this
 * kind starts with. A Page Definition renders nothing, is not itself a
 * Section, and is never persisted beyond a stable handle stored on the Page
 * row - see docs/inlay/v1/page-definitions. Register a subclass with
 * Inlay::pageDefinition()/pageDefinitions().
 */
abstract class PageDefinition
{
    /**
     * `null` (the default) means every registered Section is allowed - an
     * unrestricted Page. An explicit array restricts a Page of this kind to
     * exactly those Sections; a Section's picker category is presentational
     * only and never itself a permission boundary.
     *
     * @return array<int, class-string<InlaySection>|SectionHandle>|null
     */
    public function allowedSections(): ?array
    {
        return null;
    }

    /**
     * Sections that must appear at least once before a Page of this kind can
     * be published. A required Section is implicitly also allowed, whether or
     * not it's separately listed in allowedSections().
     *
     * @return array<int, class-string<InlaySection>|SectionHandle>
     */
    public function requiredSections(): array
    {
        return [];
    }

    /**
     * Every placed instance of a Section whose handle appears here can be
     * neither deleted nor reordered (docs/inlay/v1/page-definitions#locked). A
     * locked Section is always also required - PageDefinitionRegistry rejects
     * a definition that locks a Section without also requiring it, rather
     * than silently normalising the contradiction away.
     *
     * @return array<int, class-string<InlaySection>|SectionHandle>
     */
    public function lockedSections(): array
    {
        return [];
    }

    /**
     * Seeded, in this declared order, when a new Page of this kind is
     * created - with sparse settings, resolved from each Section's own
     * current defaults exactly like adding one by hand from the picker. May
     * repeat a handle.
     *
     * @return array<int, class-string<InlaySection>|SectionHandle>
     */
    public function defaultSections(): array
    {
        return [];
    }
}
