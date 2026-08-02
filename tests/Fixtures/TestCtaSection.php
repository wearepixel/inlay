<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\Inlay\Contracts\InlayComponent;
use WearePixel\Inlay\Schema\Settings;
use WearePixel\Inlay\Support\LinkValue;

final class TestCtaSection extends Component implements InlayComponent
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
