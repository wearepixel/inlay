<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures\Spike;

use Illuminate\View\Component;
use Illuminate\View\View;
use RuntimeException;

final class SpikeThrowingComponent extends Component
{
    public function render(): View
    {
        throw new RuntimeException('spike failure');
    }
}
