<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\Inlay\Contracts\InlayComponent;
use WearePixel\Inlay\Schema\Settings;

final class TestFaqSection extends Component implements InlayComponent
{
    public function __construct(
        public array $items = [],
    ) {}

    public static function settings(): Settings
    {
        $itemSchema = Settings::make()
            ->text('question')->label('Question')
            ->textarea('answer')->label('Answer');

        return Settings::make()->repeater('items', $itemSchema)->label('FAQ items');
    }

    public function render(): View
    {
        return view('test-faq');
    }
}
