<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageRevision;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;

final class PublishPage
{
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly SectionRenderer $renderer,
    ) {}

    public function execute(Page $page, ?int $createdBy = null, ?string $label = null): PageRevision
    {
        return DB::transaction(function () use ($page, $createdBy, $label) {
            $sections = $page->sections()
                ->orderBy('order')
                ->get()
                ->map(fn (PageSection $section) => [
                    // A published snapshot stores fully-resolved effective
                    // settings for every field, not the section's (possibly
                    // sparse) stored overrides - so a later change to the
                    // block's own constructor default never silently
                    // changes how this historical revision renders.
                    // docs/v1-implementation-specification.md §13.
                    'id' => $section->id,
                    'handle' => $section->handle,
                    'order' => $section->order,
                    'settings' => $this->renderer->resolveEffectiveRawSettings(
                        $this->registry->definition($section->handle),
                        $section->settings ?? [],
                    ),
                ])
                ->all();

            // Page-level metadata (title, meta title/description) is
            // snapshotted alongside sections for exactly the same reason
            // sections themselves are: a public visit renders the latest
            // *revision*, not the live/draft Page row, so editing a title
            // or meta description in the admin can never change what's
            // already public without an explicit Publish - the same
            // guarantee Block settings already have. `path` and
            // `is_homepage` are deliberately NOT snapshotted - they're
            // routing/identity concerns PagesController must resolve
            // against current, live state to find this Page at all, not
            // "content" a past revision should remember having had.
            $revision = $page->revisions()->create([
                'snapshot' => [
                    'sections' => $sections,
                    'title' => $page->title,
                    'meta_title' => $page->meta_title,
                    'meta_description' => $page->meta_description,
                ],
                'created_by' => $createdBy,
                'label' => $label,
            ]);

            $page->update(['status' => PageStatus::Published]);

            return $revision;
        });
    }
}
