<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Http\Controllers;

use Illuminate\Contracts\View\View;
use RuntimeException;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;

/**
 * Resolves and renders a published Page for a real public visit, through
 * Inlay::pageRoutes() - the package-owned counterpart to a host rendering a
 * Page itself via Inlay::renderPage()/<x-inlay::render> from a route it
 * already owns (docs/routing.md). Draft Pages, and Pages with no public
 * path that also aren't the homepage, are never resolved here - a normal
 * 404 either way, indistinguishable from "no route matched" to a visitor.
 */
final class PagesController
{
    public function __construct(private readonly InlayManager $inlay) {}

    public function __invoke(?string $path = null): View
    {
        $page = $this->resolvePage($path);

        if ($page === null) {
            abort(404);
        }

        $view = $this->inlay->resolvePagesView();

        if (! view()->exists($view)) {
            throw new RuntimeException(
                "Inlay could not find the public Page view [{$view}]. Create it (e.g. resources/views/".
                str_replace('.', '/', $view).'.blade.php), or configure a different one via '.
                "config('inlay.pages.view') or Inlay::pageRoutes(view: ...). See docs/routing.md."
            );
        }

        return view($view, [
            'page' => $page,
            'composition' => $this->inlay->renderPage($page),
            'context' => null,
        ]);
    }

    private function resolvePage(?string $path): ?Page
    {
        $normalized = Page::normalizePath($path);

        if ($normalized === null) {
            return Page::query()->homepage()->published()->first();
        }

        return Page::query()->published()->whereNotNull('path')->where('path', $normalized)->first();
    }
}
