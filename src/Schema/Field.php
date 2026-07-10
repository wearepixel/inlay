<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Schema;

use Closure;

final class Field
{
    public ?Closure $classResolver = null;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $name,
        public readonly FieldType $type,
        public readonly string $label,
        public readonly array $options = [],
        public readonly ?Schema $itemSchema = null,
    ) {}

    public function resolveClass(mixed $value): ?string
    {
        return $this->classResolver ? ($this->classResolver)($value) : null;
    }
}
