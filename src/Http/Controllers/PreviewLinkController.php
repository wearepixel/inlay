<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Preview\PreviewLinks;
use WeArePixel\Inlay\Preview\PreviewRenderer;

final class PreviewLinkController
{
    public function __invoke(string $token, PreviewLinks $links, PreviewRenderer $preview): Response
    {
        $entry = $links->resolve($token);

        abort_if($entry === null, 404);

        $page = Page::query()->findOrFail($entry['page_id']);

        return response($preview->render($page, $entry['sections']))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
