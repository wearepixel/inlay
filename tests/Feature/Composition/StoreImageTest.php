<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use WeArePixel\Inlay\Actions\StoreImage;
use WeArePixel\Inlay\Composition\InvalidCompositionException;

beforeEach(function () {
    Storage::fake('public');
});

function pngBytes(): string
{
    $image = imagecreatetruecolor(4, 3);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

it('stores a base64 image under a generated name and returns its disk path', function () {
    $path = app(StoreImage::class)->fromBase64('data:image/png;base64,'.base64_encode(pngBytes()));

    expect($path)->toMatch('#^inlay/[0-9a-f-]{36}\.png$#');
    Storage::disk('public')->assertExists($path);
});

it('rejects anything that is not a raster image, and stores nothing', function (string $bytes) {
    expect(fn () => app(StoreImage::class)->fromBase64(base64_encode($bytes)))
        ->toThrow(InvalidCompositionException::class);

    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'html' => ['<html><body>hi</body></html>'],
    'php disguised as png' => ["\x89PNG\r\n\x1a\n<?php system(\$_GET['c']);"],
]);

it('rejects invalid base64 and oversized images', function () {
    expect(fn () => app(StoreImage::class)->fromBase64('not base64 !!!'))->toThrow(InvalidCompositionException::class);

    config(['inlay.mcp.max_image_kilobytes' => 0]);
    expect(fn () => app(StoreImage::class)->fromBase64(base64_encode(pngBytes())))->toThrow(InvalidCompositionException::class);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('rejects an image whose declared dimensions exceed the pixel limit before decoding it', function () {
    config(['inlay.mcp.max_image_pixels' => 10]);

    expect(fn () => app(StoreImage::class)->fromBase64(base64_encode(pngBytes())))
        ->toThrow(InvalidCompositionException::class, 'pixels');

    expect(Storage::disk('public')->allFiles())->toBe([]);
});
