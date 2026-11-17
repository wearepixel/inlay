<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Http\Controllers;

use Illuminate\Contracts\View\View;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Preview\PreviewRenderer;

final class PagePreviewController
{
    public function __invoke(Page $page, PreviewRenderer $preview, InlayManager $inlay): View
    {
        $inlay->authorizeAdmin($page);

        return $preview->render($page);
    }
}
