<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Attributes\Link;
use WeArePixel\Inlay\Contracts\InlayBlock;
use WeArePixel\Inlay\Support\LinkValue;

/**
 * A non-nullable Link field with a real default, mirroring how a
 * non-nullable ImageValue default already works (`new ImageValue()`) -
 * see README.md#linkvalue. Deliberately distinct from TestCtaSection's
 * `?LinkValue $cta = null`, which never exercised the raw-settings path
 * for a real LinkValue default.
 */
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
