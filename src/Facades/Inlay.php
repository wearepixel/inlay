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
 * @method static void singleFileBlock(string $path)
 * @method static void singleFileBlocks(string $directory)
 * @method static void authorizeAdmin(?Page $page = null)
 * @method static void adminUrlsUsing(?\Closure $resolver)
 * @method static string adminUrl(string $screen, mixed ...$parameters)
 * @method static void siteSettings(string $handle, \WeArePixel\Inlay\Schema\Settings $schema, ?string $label = null)
 * @method static mixed setting(string $key, mixed $default = null)
 * @method static array<string, mixed> settings(string $handle)
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
