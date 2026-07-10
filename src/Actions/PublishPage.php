<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Actions;

use Illuminate\Support\Facades\DB;
use WearePixel\BladePageBuilder\Enums\PageStatus;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Models\PageRevision;
use WearePixel\BladePageBuilder\Models\PageSection;

final class PublishPage
{
    public function execute(Page $page, ?int $createdBy = null, ?string $label = null): PageRevision
    {
        return DB::transaction(function () use ($page, $createdBy, $label) {
            $snapshot = $page->sections()
                ->orderBy('order')
                ->get()
                ->map(fn (PageSection $section) => [
                    'section_type' => $section->section_type,
                    'order' => $section->order,
                    'props' => $section->props,
                ])
                ->all();

            $revision = $page->revisions()->create([
                'snapshot' => $snapshot,
                'created_by' => $createdBy,
                'label' => $label,
            ]);

            $page->update(['status' => PageStatus::Published]);

            return $revision;
        });
    }
}
