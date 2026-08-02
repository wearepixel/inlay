<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use WeArePixel\Inlay\Support\DescribesSettingOptions;

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
