<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Attributes\Link;
use WeArePixel\Inlay\Contracts\InlayBlock;
use WeArePixel\Inlay\Support\LinkValue;

// Non-nullable Link default, distinct from TestCtaSection's `?LinkValue $cta = null`.
final class TestCtaWithDefaultSection extends Component implements InlayBlock
{
    public function __construct(
        #[Link(label: 'Call to action')]
        public LinkValue $cta = new LinkValue(label: 'Get started', url: '/menu'),
    ) {}

    public function render(): View
    {
        return view('test-cta');
    }
}
