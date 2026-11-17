<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Attributes\Image;
use WeArePixel\Inlay\Attributes\SectionMeta;
use WeArePixel\Inlay\Attributes\Select;
use WeArePixel\Inlay\Attributes\Text;
use WeArePixel\Inlay\Contracts\InlaySection;
use WeArePixel\Inlay\Support\ImageValue;

#[SectionMeta(label: 'Hero', category: 'Headers', description: 'A large banner with a heading and image.', icon: '🖼')]
final class TestHeroSection extends Component implements InlaySection
{
    public function __construct(
        #[Text(label: 'Heading')]
        public string $heading = '',
        #[Select(label: 'Layout')]
        public TestHeroLayout $layout = TestHeroLayout::ImageRight,
        #[Image(label: 'Photo')]
        public ?ImageValue $photo = null,
    ) {}

    /**
     * The mapsToClass() mechanic is removed - a section maps a setting to
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
