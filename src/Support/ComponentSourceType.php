<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

/**
 * What authored a registered component - purely descriptive. Nothing
 * downstream of ComponentDefinition (the registry, rendering, the picker,
 * persistence, publishing, revisions) is allowed to branch on this except
 * SectionRenderer, which needs it to choose how to render the two
 * fundamentally different source shapes (a PHP class vs. a Blade file).
 */
enum ComponentSourceType
{
    case ClassBased;
    case SingleFile;
}
