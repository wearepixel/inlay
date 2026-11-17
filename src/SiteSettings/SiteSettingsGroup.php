<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\SiteSettings;

use WeArePixel\Inlay\Schema\Settings;

final readonly class SiteSettingsGroup
{
    public function __construct(
        public string $handle,
        public string $label,
        public Settings $schema,
    ) {}
}
