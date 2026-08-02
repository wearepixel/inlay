<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Contracts\InlayBlock;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\LinkValue;

final class TestCtaSection extends Component implements InlayBlock
{
    public function __construct(
        public ?LinkValue $cta = null,
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()->link('cta')->label('Call to action');
    }

    public function render(): View
    {
        return view('test-cta');
    }
}
