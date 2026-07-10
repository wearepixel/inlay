<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Support;

use InvalidArgumentException;
use WearePixel\BladePageBuilder\Contracts\Sectionable;

final class SectionRegistry
{
    /**
     * @param array<string, class-string<Sectionable>> $sections
     */
    public function __construct(private readonly array $sections) {}

    public static function fromConfig(): self
    {
        return new self(config('blade-page-builder.sections', []));
    }

    /**
     * @return class-string<Sectionable>
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
