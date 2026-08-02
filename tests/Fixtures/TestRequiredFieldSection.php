<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\Inlay\Attributes\Select;
use WearePixel\Inlay\Attributes\Text;
use WearePixel\Inlay\Contracts\InlayComponent;

final class TestRequiredFieldSection extends Component implements InlayComponent
{
    public function __construct(
        #[Text(label: 'Heading', required: true)]
        public string $heading = '',
        #[Select(label: 'Size')]
        public TestSizeWithDescription $size = TestSizeWithDescription::Large,
    ) {}

    public function render(): View
    {
        return view('test-required-field');
    }
}
