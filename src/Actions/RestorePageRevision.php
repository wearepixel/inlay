<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Models\PageRevision;

final class RestorePageRevision
{
    public function __construct(private readonly PublishPage $publishPage) {}

    public function execute(PageRevision $revision, ?int $createdBy = null): PageRevision
    {
        return DB::transaction(function () use ($revision, $createdBy) {
            $page = $revision->page;

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

            return $this->publishPage->execute(
                $page,
                createdBy: $createdBy,
                label: "Restored from revision #{$revision->id}",
            );
        });
    }
}
