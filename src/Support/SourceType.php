<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

/**
 * What authored a registered section - purely descriptive. Nothing
 * downstream of Definition (the registry, rendering, the picker,
 * persistence, publishing, revisions) is allowed to branch on this except
 * SectionRenderer, which needs it to choose how to render the two
 * fundamentally different source shapes (a PHP class vs. a Blade file).
 */
enum SourceType
{
    case ClassBased;
    case SingleFile;
    case Declarative;
}
