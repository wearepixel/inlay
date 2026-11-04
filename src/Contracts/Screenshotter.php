<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Contracts;

use WeArePixel\Inlay\Screenshots\Screenshot;

/**
 * Captures a URL as an image. Bind your own implementation to use a hosted
 * renderer instead of a local headless browser.
 */
interface Screenshotter
{
    public function capture(string $url, int $width): Screenshot;
}
