<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Attributes;

use WeArePixel\Inlay\Schema\Setting;

/**
 * Implemented by every supported inlay attribute (Text, Toggle, Select,
 * ...). ComponentDefinitionResolver looks for any attribute implementing this
 * interface on a constructor parameter, rather than checking each concrete
 * attribute class by name one at a time.
 */
interface FieldAttribute
{
    public function toSetting(string $name): Setting;
}
