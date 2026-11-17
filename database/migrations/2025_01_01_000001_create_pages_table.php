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
            // see docs/inlay/v1/routing.
            $table->boolean('is_homepage')->default(false);
            // The registered Page Definition handle this Page follows
            // (docs/inlay/v1/page-definitions) - never the PHP class itself,
            // matching how a PageSection stores a Section's handle rather
            // than its class. Nullable, and treated everywhere as "use the
            // built-in, unrestricted default definition" - existing Pages
            // from before this column existed get that same default rather
            // than needing a backfill. A handle that no longer matches
            // anything registered (PageDefinitionRegistry::resolveForPage()
            // returns null) is surfaced as an admin warning, not silently
            // reinterpreted as the default.
            $table->string('definition')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
