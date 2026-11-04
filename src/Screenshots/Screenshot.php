<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Screenshots;

final readonly class Screenshot
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public int $width,
    ) {}
}
