<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Facades;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Facade;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinition;

/**
 * @method static void routes()
 * @method static void adminRoutes()
 * @method static void pageRoutes(?string $prefix = null, ?string $view = null, ?array<int, string> $middleware = null)
 * @method static ?string url(Page $page)
 * @method static void previewView(string $view)
 * @method static ?string resolvePreviewView()
 * @method static string resolvePagesView()
 * @method static Htmlable renderPage(Page $page, ?array<string, mixed> $context = null)
 * @method static void pageDefinition(class-string<PageDefinition> $definitionClass)
 * @method static void pageDefinitions(array<int, class-string<PageDefinition>> $definitionClasses)
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
