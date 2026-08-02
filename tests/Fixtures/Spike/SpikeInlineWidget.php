<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures\Spike;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class SpikeInlineWidget extends Component
{
    public function render(): View
    {
        return view('spike-inline-widget');
    }
}
