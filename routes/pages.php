<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WeArePixel\Inlay\Http\Controllers\PagesController;

// A genuine catch-all within this group: registered under whatever prefix
// (or none) Inlay::pageRoutes() resolved, and - for an unprefixed, root
// registration - a real catch-all across the whole application, which is
// why it must be registered last, after the host's own routes
// (docs/inlay/v1/routing). The '.*' constraint on {path} deliberately allows
// slashes through, so a nested path like "about/team" round-trips through
// route('inlay.pages.show', ['path' => 'about/team']) for URL generation
// without being percent-encoded - see InlayManager::url().
Route::get('/{path?}', PagesController::class)->where('path', '.*')->name('show');
