<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures\Spike;

final class SpikeRepository
{
    public function label(): string
    {
        return 'resolved-from-container';
    }
}
