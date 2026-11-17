<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Contracts\InlayBlock;
use WeArePixel\Inlay\Schema\Settings;

final class TestRichTextSection extends Component implements InlayBlock
{
    public function __construct(
        public string $body = '',
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()->richtext('body')->label('Body');
    }

    public function render(): View
    {
        return view('test-faq', ['items' => []]);
    }
}
