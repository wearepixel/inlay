<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Enums;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
