<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Enums;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
