<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\BladePageBuilder\Contracts\Sectionable;
use WearePixel\BladePageBuilder\Schema\Schema;

final class TestHeroSection extends Component implements Sectionable
{
    public function __construct(
        public string $heading = '',
        public string $layout = 'image-right',
    ) {}

    public static function schema(): Schema
    {
        return Schema::make()
            ->text('heading', label: 'Heading')
            ->select('layout', label: 'Layout', options: [
                'image-left' => 'Image on left',
                'image-right' => 'Image on right',
            ])->mapsToClass(fn (string $value) => $value === 'image-left' ? 'flex-row-reverse' : 'flex-row');
    }

    public function with(): array
    {
        return [
            'layoutClass' => static::schema()->field('layout')?->resolveClass($this->layout),
        ];
    }

    public function render(): View
    {
        return view('test-hero');
    }
}
