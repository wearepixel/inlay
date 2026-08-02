<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WearePixel\Inlay\Enums\PageStatus;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Models\PageRevision;
use WearePixel\Inlay\Models\PageSection;
use WearePixel\Inlay\Support\SectionRegistry;
use WearePixel\Inlay\Support\SectionRenderer;

final class PublishPage
{
    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly SectionRenderer $renderer,
    ) {}

    public function execute(Page $page, ?int $createdBy = null, ?string $label = null): PageRevision
    {
        return DB::transaction(function () use ($page, $createdBy, $label) {
            $snapshot = $page->sections()
                ->orderBy('order')
                ->get()
                ->map(fn (PageSection $section) => [
                    // A published snapshot stores fully-resolved effective
                    // settings for every field, not the section's (possibly
                    // sparse) stored overrides - so a later change to the
                    // component's own constructor default never silently
                    // changes how this historical revision renders.
                    // docs/v1-implementation-specification.md §13.
                    'id' => $section->id,
                    'handle' => $section->handle,
                    'order' => $section->order,
                    'settings' => $this->renderer->resolveEffectiveRawSettings(
                        $this->registry->resolve($section->handle),
                        $section->settings ?? [],
                    ),
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
