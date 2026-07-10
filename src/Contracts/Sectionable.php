<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Contracts;

use WearePixel\BladePageBuilder\Schema\Schema;

interface Sectionable
{
    public static function schema(): Schema;
}
