<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use WeArePixel\Inlay\Actions\StoreImage;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Mcp\InlayTool;

#[Name('inlay-upload-image')]
#[Title('Upload an image for Inlay')]
#[Description(<<<'TEXT'
Uploads an image (JPEG, PNG, WebP or GIF, base64-encoded) so it can be used in an image setting.
Returns a "path": put that path in the block's image setting. Export images at the size they are
displayed - for example from a Figma frame - rather than uploading a much larger original. SVG is not
accepted.
TEXT)]
final class UploadImageTool extends InlayTool
{
    public function handle(Request $request, StoreImage $store): Response|ResponseFactory
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        $data = $request->get('data');

        if (! is_string($data) || $data === '') {
            return Response::error('"data" must be the base64-encoded image.');
        }

        if ($throttled = $this->throttle($request)) {
            return $throttled;
        }

        try {
            $path = $store->fromBase64($data);
        } catch (InvalidCompositionException $exception) {
            return $this->invalid($exception);
        }

        return Response::structured([
            'path' => $path,
            'url' => Storage::disk(config('inlay.disk', 'public'))->url($path),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->string()->description('Base64 image bytes, optionally as a data: URI.')->required(),
        ];
    }
}
