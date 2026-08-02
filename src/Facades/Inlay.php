<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Facades;

use Illuminate\Support\Facades\Facade;
use WeArePixel\Inlay\InlayManager;

/**
 * @method static void routes()
 * @method static void previewView(string $view)
 * @method static ?string resolvePreviewView()
 *
 * @see InlayManager
 */
final class Inlay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return InlayManager::class;
    }
}
