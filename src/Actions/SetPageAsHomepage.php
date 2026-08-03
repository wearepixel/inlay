<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Models\Page;

/**
 * At most one Page may be the homepage at a time. There is no separate
 * site-settings record to point at it - `is_homepage` lives directly on the
 * Page row (docs/routing.md §homepage), which also means "delete the
 * homepage" needs no dedicated cleanup: the flag simply goes with the row.
 */
final class SetPageAsHomepage
{
    public function execute(Page $page): void
    {
        DB::transaction(function () use ($page) {
            Page::query()
                ->where('id', '!=', $page->id)
                ->where('is_homepage', true)
                ->update(['is_homepage' => false]);

            $page->update(['is_homepage' => true]);
        });
    }
}
