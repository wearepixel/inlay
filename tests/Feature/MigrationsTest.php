<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates the pages, page_sections, and page_revisions tables', function () {
    expect(Schema::hasTable('pages'))->toBeTrue()
        ->and(Schema::hasColumns('pages', ['title', 'path', 'status', 'meta_title', 'meta_description', 'is_homepage']))->toBeTrue()
        ->and(Schema::hasTable('page_sections'))->toBeTrue()
        ->and(Schema::hasColumns('page_sections', ['page_id', 'handle', 'order', 'settings']))->toBeTrue()
        ->and(Schema::hasTable('page_revisions'))->toBeTrue()
        ->and(Schema::hasColumns('page_revisions', ['page_id', 'snapshot', 'created_by', 'label']))->toBeTrue();
});
