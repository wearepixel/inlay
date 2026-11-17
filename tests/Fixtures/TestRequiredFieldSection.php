<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Attributes\Select;
use WeArePixel\Inlay\Attributes\Text;
use WeArePixel\Inlay\Contracts\InlaySection;

final class TestRequiredFieldSection extends Component implements InlaySection
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
