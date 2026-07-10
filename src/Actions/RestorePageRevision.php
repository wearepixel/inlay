<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Actions;

use Illuminate\Support\Facades\DB;
use WearePixel\BladePageBuilder\Models\PageRevision;

final class RestorePageRevision
{
    public function __construct(private readonly PublishPage $publishPage) {}

    public function execute(PageRevision $revision, ?int $createdBy = null): PageRevision
    {
        return DB::transaction(function () use ($revision, $createdBy) {
            $page = $revision->page;

            $page->sections()->delete();

            foreach ($revision->snapshot as $sectionData) {
                $page->sections()->create($sectionData);
            }

            return $this->publishPage->execute(
                $page,
                createdBy: $createdBy,
                label: "Restored from revision #{$revision->id}",
            );
        });
    }
}
