<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Attributes\Text;
use WeArePixel\Inlay\Contracts\InlayBlock;

/**
 * `viewerName` is deliberately NOT declared as a setting (no attribute, no
 * settings() entry) - it exists purely to prove a host-supplied $context
 * value reaches a block's own constructor property the same way an
 * ordinary container-resolvable dependency would, without Inlay's schema
 * system needing to know about it at all.
 */
final class TestContextAwareSection extends Component implements InlayBlock
{
    public function __construct(
        #[Text(label: 'Heading')]
        public string $heading = '',
        public string $viewerName = '',
    ) {}

    public function render(): View
    {
        return view('test-context-aware');
    }
}
