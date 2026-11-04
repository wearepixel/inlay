<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Apps;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Csp;

#[Name('inlay-preview')]
#[Uri('ui://inlay/preview')]
#[Description('Shows an Inlay page preview inline, at desktop or mobile width.')]
final class PreviewApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('inlay::mcp.preview-app');
    }

    /**
     * The preview is a nested iframe of this application's signed preview
     * link, so the app's own origin has to be allowed as a frame source.
     */
    public function appMeta(): AppMeta
    {
        $origin = rtrim((string) config('app.url'), '/');

        return AppMeta::make()
            ->csp(Csp::make()->frameDomains($origin === '' ? [] : [$origin]))
            ->prefersBorder();
    }
}
