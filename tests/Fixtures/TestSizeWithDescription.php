<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Tests\Fixtures;

use WearePixel\Inlay\Support\DescribesSettingOptions;

enum TestSizeWithDescription: string implements DescribesSettingOptions
{
    case Small = 'small';
    case Large = 'large';

    public function optionDescription(): ?string
    {
        return match ($this) {
            self::Small => null,
            self::Large => 'Full-width, best for a strong first impression.',
        };
    }
}
