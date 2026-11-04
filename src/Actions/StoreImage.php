<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use WeArePixel\Inlay\Composition\CompositionError;
use WeArePixel\Inlay\Composition\InvalidCompositionException;

/**
 * Stores an image supplied as base64 on the Inlay disk. The bytes must
 * parse as a raster image of an allowed type - SVG is refused because it
 * can carry script - and the stored name is always generated, never taken
 * from the caller.
 */
final class StoreImage
{
    private const EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    /**
     * @throws InvalidCompositionException
     */
    public function fromBase64(string $data): string
    {
        $encoded = (string) preg_replace('#^data:[\w/+.-]+;base64,#', '', trim($data));
        $maxKilobytes = (int) config('inlay.mcp.max_image_kilobytes', 5120);

        if (strlen($encoded) > (int) ceil($maxKilobytes * 1024 * 4 / 3) + 4) {
            $this->fail("Images must be at most {$maxKilobytes} KB.");
        }

        $bytes = base64_decode($encoded, strict: true);

        if ($bytes === false || $bytes === '') {
            $this->fail('Must be base64-encoded image data (a data: URI prefix is allowed).');
        }

        if (strlen($bytes) > $maxKilobytes * 1024) {
            $this->fail("Images must be at most {$maxKilobytes} KB.");
        }

        $info = @getimagesizefromstring($bytes);
        $extension = $info !== false ? (self::EXTENSIONS[$info[2]] ?? null) : null;
        $maxPixels = (int) config('inlay.mcp.max_image_pixels', 40_000_000);

        if ($info !== false && $maxPixels < $info[0] * $info[1]) {
            $this->fail("Images must be at most {$maxPixels} pixels in total. Export it at the size it is displayed.");
        }

        if ($extension === null || ! $this->decodes($bytes)) {
            $this->fail('Must be a JPEG, PNG, WebP or GIF image.');
        }

        $path = 'inlay/'.Str::uuid().'.'.$extension;

        Storage::disk(config('inlay.disk', 'public'))->put($path, $bytes);

        return $path;
    }

    /**
     * getimagesize only reads the header, so a valid PNG signature followed by
     * a script would pass it. Decoding the whole image proves the bytes are
     * an image and not just labelled as one.
     */
    private function decodes(string $bytes): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return true;
        }

        return @imagecreatefromstring($bytes) !== false;
    }

    private function fail(string $message): never
    {
        throw new InvalidCompositionException([new CompositionError('image', $message)]);
    }
}
