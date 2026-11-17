<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp;

use WeArePixel\Inlay\Mcp\Apps\PreviewApp;
use WeArePixel\Inlay\Mcp\Tools\CreatePageTool;
use WeArePixel\Inlay\Mcp\Tools\DeleteSectionTool;
use WeArePixel\Inlay\Mcp\Tools\GetPageTool;
use WeArePixel\Inlay\Mcp\Tools\GetSectionTool;
use WeArePixel\Inlay\Mcp\Tools\GetStylingTool;
use WeArePixel\Inlay\Mcp\Tools\ListPageDefinitionsTool;
use WeArePixel\Inlay\Mcp\Tools\ListPagesTool;
use WeArePixel\Inlay\Mcp\Tools\ListSectionsTool;
use WeArePixel\Inlay\Mcp\Tools\PreviewPageTool;
use WeArePixel\Inlay\Mcp\Tools\PublishPageTool;
use WeArePixel\Inlay\Mcp\Tools\ReplaceSectionsTool;
use WeArePixel\Inlay\Mcp\Tools\SaveSectionTool;
use WeArePixel\Inlay\Mcp\Tools\UpdateSectionTool;
use WeArePixel\Inlay\Mcp\Tools\UploadImageTool;

/**
 * For mounting Inlay inside a host's existing MCP server:
 *
 *     protected array $tools = [MyTool::class, ...InlayTools::all()];
 *     protected array $resources = [...InlayTools::resources()];
 */
final class InlayTools
{
    public const TOOLS = [
        ListSectionsTool::class,
        ListPageDefinitionsTool::class,
        ListPagesTool::class,
        GetPageTool::class,
        CreatePageTool::class,
        ReplaceSectionsTool::class,
        UpdateSectionTool::class,
        UploadImageTool::class,
        PreviewPageTool::class,
        PublishPageTool::class,
        GetStylingTool::class,
        SaveSectionTool::class,
        GetSectionTool::class,
        DeleteSectionTool::class,
    ];

    public const RESOURCES = [PreviewApp::class];

    /**
     * @return array<int, class-string<InlayTool>>
     */
    public static function all(): array
    {
        return self::TOOLS;
    }

    /**
     * @return array<int, class-string>
     */
    public static function resources(): array
    {
        return self::RESOURCES;
    }
}
