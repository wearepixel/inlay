<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // The canonical public path, e.g. "about" or "test/test" - never
            // a full URL, never including the route-group prefix a host
            // configures via Inlay::pageRoutes(prefix: ...), which is purely
            // routing configuration and is layered on at request/URL-
            // generation time, not persisted. Nullable: a Page with no path
            // is valid and intentional - it's host-rendered only (via
            // Inlay::renderPage() or <x-inlay::render>), never resolved by
            // Inlay's own public routing. Multiple NULLs don't violate the
            // unique index below on any of this package's supported
            // database drivers.
            $table->string('path')->nullable()->unique();
            $table->string('status')->default('draft');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            // At most one Page may have this set at a time - enforced in
            // SetPageAsHomepage, not at the schema level, since "exactly one
            // true" isn't expressible as a portable index across this
            // package's supported drivers without a driver-specific partial
            // index. A homepage's own `path` is irrelevant to resolving it -
            // see docs/routing.md.
            $table->boolean('is_homepage')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
