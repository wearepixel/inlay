<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

use WeArePixel\Inlay\Contracts\InlayBlock;
use WeArePixel\Inlay\Support\BlockHandle;

/**
 * Developer-owned composition *policy* for a kind of Page - which Blocks it
 * may contain, which are required or locked, and what a new Page of this
 * kind starts with. A Page Definition renders nothing, is not itself a
 * Block, and is never persisted beyond a stable handle stored on the Page
 * row - see docs/inlay/v1/page-definitions. Register a subclass with
 * Inlay::pageDefinition()/pageDefinitions().
 */
abstract class PageDefinition
{
    /**
     * `null` (the default) means every registered Block is allowed - an
     * unrestricted Page. An explicit array restricts a Page of this kind to
     * exactly those Blocks; a Block's picker category is presentational
     * only and never itself a permission boundary.
     *
     * @return array<int, class-string<InlayBlock>|BlockHandle>|null
     */
    public function allowedBlocks(): ?array
    {
        return null;
    }

    /**
     * Blocks that must appear at least once before a Page of this kind can
     * be published. A required Block is implicitly also allowed, whether or
     * not it's separately listed in allowedBlocks().
     *
     * @return array<int, class-string<InlayBlock>|BlockHandle>
     */
    public function requiredBlocks(): array
    {
        return [];
    }

    /**
     * Every placed instance of a Block whose handle appears here can be
     * neither deleted nor reordered (docs/inlay/v1/page-definitions#locked). A
     * locked Block is always also required - PageDefinitionRegistry rejects
     * a definition that locks a Block without also requiring it, rather
     * than silently normalising the contradiction away.
     *
     * @return array<int, class-string<InlayBlock>|BlockHandle>
     */
    public function lockedBlocks(): array
    {
        return [];
    }

    /**
     * Seeded, in this declared order, when a new Page of this kind is
     * created - with sparse settings, resolved from each Block's own
     * current defaults exactly like adding one by hand from the picker. May
     * repeat a handle.
     *
     * @return array<int, class-string<InlayBlock>|BlockHandle>
     */
    public function defaultBlocks(): array
    {
        return [];
    }
}
