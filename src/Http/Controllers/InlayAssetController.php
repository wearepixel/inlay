<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the admin's own hand-authored, self-hosted CSS/JS with zero build
 * step and zero CDN dependency - the assets ship as plain files inside the
 * package and are read from a fixed, package-internal path, never from
 * user input.
 */
final class InlayAssetController
{
    public function css(): BinaryFileResponse
    {
        return $this->file('inlay.css', 'text/css');
    }

    public function js(): BinaryFileResponse
    {
        return $this->file('inlay.js', 'application/javascript');
    }

    private function file(string $filename, string $contentType): BinaryFileResponse
    {
        return response()->file(__DIR__.'/../../../resources/dist/'.$filename, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
