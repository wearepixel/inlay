<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Htmlable;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * What a blocks field hands a section's view: {{ $content }} renders every
 * block in order, or loop over it to wrap each one.
 *
 * @implements IteratorAggregate<int, RenderedBlock>
 */
final readonly class BlockList implements Countable, Htmlable, IteratorAggregate, Stringable
{
    /**
     * @param  array<int, RenderedBlock>  $blocks
     */
    public function __construct(private array $blocks = []) {}

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    public function count(): int
    {
        return count($this->blocks);
    }

    /**
     * @return Traversable<int, RenderedBlock>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->blocks);
    }

    public function toHtml(): string
    {
        return implode('', array_map(fn (RenderedBlock $block) => $block->toHtml(), $this->blocks));
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
