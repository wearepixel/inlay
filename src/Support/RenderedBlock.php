<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use Illuminate\Contracts\Support\Htmlable;
use Stringable;

final readonly class RenderedBlock implements Htmlable, Stringable
{
    /**
     * @param  array<string, mixed>  $settings  the block's hydrated settings
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $settings,
        private string $html,
    ) {}

    public function toHtml(): string
    {
        return $this->html;
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
