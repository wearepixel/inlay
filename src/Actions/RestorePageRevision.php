<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageRevision;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\PageDefinitionValidator;

final class RestorePageRevision
{
    public function __construct(
        private readonly PublishPage $publishPage,
        private readonly PageDefinitionValidator $validator,
        private readonly PageDefinitionRegistry $definitions,
    ) {}

    /**
     * Restoring a revision's content into the live draft always succeeds,
     * even when that content no longer satisfies the Page's *current* Page
     * Definition (a definition can tighten after a revision was published -
     * docs/inlay/v1/page-definitions#revisions-and-restore). What happens
     * next depends on whether it does:
     *
     * - Satisfies it: restoring is itself a publish, exactly as before this
     *   feature existed - a new revision is created immediately.
     * - Violates it: the restored content lands in the draft only. No new
     *   revision is created, the Page's status is untouched, and this
     *   returns null so the caller can show the violations rather than
     *   reporting a restore that didn't actually happen. Publishing stays
     *   blocked (PublishPage enforces this identically) until the draft is
     *   corrected and re-published.
     *
     * Either way, the Page's own `definition` is never switched back to
     * whatever the revision recorded - a restore brings back content, not
     * the rules that applied to it at the time.
     */
    public function execute(PageRevision $revision, ?int $createdBy = null): ?PageRevision
    {
        return DB::transaction(function () use ($revision, $createdBy) {
            $page = $revision->page;
            $resolvedDefinition = $this->definitions->resolveForPage($page);
            $lockedHandles = $resolvedDefinition !== null ? $resolvedDefinition->lockedBlocks : [];
            $lockedPatternBefore = $this->lockedPositionPattern($page, $lockedHandles);

            $page->sections()->delete();

            foreach ($revision->snapshot['sections'] as $sectionData) {
                // The snapshot's own `id` refers to the section row that
                // existed when this revision was published, which no longer
                // exists (it was just deleted above) - a fresh row gets a
                // fresh id. The snapshot's fully-resolved `settings` are
                // written back verbatim as explicit overrides on that new
                // row, per docs/v1-implementation-specification.md §13 -
                // never reverse-engineered into "which values were once
                // inherited defaults."
                $page->sections()->create([
                    'handle' => $sectionData['handle'],
                    'order' => $sectionData['order'],
                    'settings' => $sectionData['settings'],
                ]);
            }

            // Page-level metadata is restored onto the live Page row too,
            // the same as sections - restoring an older revision should
            // bring back the title/meta description it was published with,
            // not just its content. `path` and `is_homepage` are
            // deliberately left untouched - see PublishPage's docblock for
            // why they're routing/identity state, not revisioned content.
            $page->update([
                'title' => $revision->snapshot['title'],
                'meta_title' => $revision->snapshot['meta_title'],
                'meta_description' => $revision->snapshot['meta_description'],
            ]);

            // Ordinary editing can never move a locked block - updateOrder()
            // rejects it outright - so this is the one path that writes
            // sections directly, bypassing that guard. A revision published
            // before a Block was locked (or before it was locked in its
            // current position) can restore content whose locked block has
            // since shifted; that's treated the same as any other
            // violation - draft-only, publish blocked - rather than
            // silently letting a restore do what a live reorder cannot.
            if ($lockedPatternBefore !== $this->lockedPositionPattern($page, $lockedHandles)) {
                return null;
            }

            if ($this->validator->validate($page) !== []) {
                return null;
            }

            return $this->publishPage->execute(
                $page,
                createdBy: $createdBy,
                label: "Restored from revision #{$revision->id}",
            );
        });
    }

    /**
     * The page's sections, in order, reduced to just which positions hold a
     * locked block's handle (and which one) - comparable before and after a
     * restore even though every row's own id changes, since it only cares
     * about relative position and handle, never row identity.
     *
     * @param  array<int, string>  $lockedHandles
     * @return array<int, string|null>
     */
    private function lockedPositionPattern(Page $page, array $lockedHandles): array
    {
        if ($lockedHandles === []) {
            return [];
        }

        return $page->sections()
            ->orderBy('order')
            ->pluck('handle')
            ->map(fn (string $handle): ?string => in_array($handle, $lockedHandles, true) ? $handle : null)
            ->all();
    }
}
