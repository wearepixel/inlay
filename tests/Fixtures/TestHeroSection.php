<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\Inlay\Attributes\Image;
use WearePixel\Inlay\Attributes\Select;
use WearePixel\Inlay\Attributes\Text;
use WearePixel\Inlay\Contracts\InlayComponent;

final class TestHeroSection extends Component implements InlayComponent
{
    public function __construct(
        #[Text(label: 'Heading')]
        public string $heading = '',
        #[Select(label: 'Layout')]
        public TestHeroLayout $layout = TestHeroLayout::ImageRight,
        #[Image(label: 'Photo')]
        public ?string $photo = null,
    ) {}

    /**
     * The mapsToClass() mechanic is removed - a component maps a setting to
     * presentation with an ordinary computed method on its own class.
     * docs/v1-implementation-specification.md §9.
     */
    public function layoutClass(): string
    {
        return match ($this->layout) {
            TestHeroLayout::ImageLeft => 'flex-row-reverse',
            TestHeroLayout::ImageRight => 'flex-row',
        };
    }

    public function render(): View
    {
        return view('test-hero');
    }
}
