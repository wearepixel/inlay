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

            foreach ($revision->snapshot as $sectionData) {
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

            return $this->publishPage->execute(
                $page,
                createdBy: $createdBy,
                label: "Restored from revision #{$revision->id}",
            );
        });
    }
}
