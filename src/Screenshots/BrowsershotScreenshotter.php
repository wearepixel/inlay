<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Screenshots;

use Spatie\Browsershot\Browsershot;
use WeArePixel\Inlay\Contracts\Screenshotter;

final class BrowsershotScreenshotter implements Screenshotter
{
    private const VIEWPORT_HEIGHT = 900;

    /**
     * @param  array{chrome_path?: string|null, node_modules_path?: string|null, no_sandbox?: bool, timeout?: int, quality?: int}  $options
     */
    public function __construct(private readonly array $options = []) {}

    public function capture(string $url, int $width): Screenshot
    {
        $browsershot = Browsershot::url($url)
            ->windowSize($width, self::VIEWPORT_HEIGHT)
            ->fullPage()
            ->waitUntilNetworkIdle()
            ->disableRedirects()
            ->timeout($this->options['timeout'] ?? 60)
            ->setScreenshotType('jpeg', $this->options['quality'] ?? 70);

        if (($this->options['chrome_path'] ?? null) !== null) {
            $browsershot->setChromePath($this->options['chrome_path']);
        }

        if (($this->options['node_modules_path'] ?? null) !== null) {
            $browsershot->setNodeModulePath($this->options['node_modules_path']);
        }

        if ($this->options['no_sandbox'] ?? false) {
            $browsershot->noSandbox();
        }

        return new Screenshot($browsershot->screenshot(), 'image/jpeg', $width);
    }
}
