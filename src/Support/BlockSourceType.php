<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

/**
 * What authored a registered block - purely descriptive. Nothing
 * downstream of BlockDefinition (the registry, rendering, the picker,
 * persistence, publishing, revisions) is allowed to branch on this except
 * SectionRenderer, which needs it to choose how to render the two
 * fundamentally different source shapes (a PHP class vs. a Blade file).
 */
enum BlockSourceType
{
    case ClassBased;
    case SingleFile;
    case Declarative;
}
