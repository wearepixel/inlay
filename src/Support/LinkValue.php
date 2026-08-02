<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

final readonly class LinkValue
{
    public function __construct(
        public string $label = '',
        public string $url = '',
        public bool $openInNewTab = false,
    ) {}
}
