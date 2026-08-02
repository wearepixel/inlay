<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

use InvalidArgumentException;
use WearePixel\Inlay\Contracts\InlayComponent;

final class SectionRegistry
{
    /**
     * @param  array<string, class-string<InlayComponent>>  $sections
     */
    public function __construct(private readonly array $sections) {}

    public static function fromConfig(): self
    {
        return new self(config('inlay.sections', []));
    }

    /**
     * @return class-string<InlayComponent>
     */
    public function resolve(string $slug): string
    {
        if (! isset($this->sections[$slug])) {
            throw new InvalidArgumentException("No section registered for slug [{$slug}].");
        }

        return $this->sections[$slug];
    }

    /**
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return array_keys($this->sections);
    }
}
