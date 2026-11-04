<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Models\Page;

/**
 * At most one Page may be the homepage at a time. There is no separate
 * site-settings record to point at it - `is_homepage` lives directly on the
 * Page row (docs/inlay/v1/routing#the-homepage), which also means "delete the
 * homepage" needs no dedicated cleanup: the flag simply goes with the row.
 *
 * There is no Sites subsystem yet, so uniqueness is enforced globally.
 * Every query here is written so that adding a Site later is a single
 * `->where('site_id', $page->site_id)` added to each one, not a rewrite of
 * the mechanism itself.
 *
 * Concurrency: locking the current homepage row(s) first serializes this
 * against another call reassigning the *same* existing homepage - by far
 * the common real case (an editor changing which Page is the homepage).
 * It does not, and cannot portably, prevent two transactions concurrently
 * promoting two *different* Pages to homepage when neither currently holds
 * the flag (there is no existing row for either to lock) - a true "exactly
 * one" guarantee needs a database-specific partial unique index, which
 * isn't expressible identically across this package's supported drivers
 * (see the pages migration's own comment on `is_homepage`). That residual
 * case is handled instead by making public resolution deterministic under
 * malformed multi-homepage data, rather than pretending the write side can
 * fully prevent it - see Page::scopeHomepage()'s use in PagesController.
 */
final class SetPageAsHomepage
{
    public function execute(Page $page): void
    {
        DB::transaction(function () use ($page) {
            Page::query()->where('is_homepage', true)->lockForUpdate()->get();

            Page::query()
                ->where('id', '!=', $page->id)
                ->where('is_homepage', true)
                ->update(['is_homepage' => false]);

            $page->update(['is_homepage' => true]);
        });
    }
}
