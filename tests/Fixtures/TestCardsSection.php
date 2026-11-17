<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Contracts\InlaySection;
use WeArePixel\Inlay\Schema\Settings;

final class TestCardsSection extends Component implements InlaySection
{
    public function __construct(
        public array $cards = [],
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()->repeater('cards', Settings::make()
            ->text('title')->label('Title')
            ->image('photo')->label('Photo'));
    }

    public function render(): View
    {
        return view('test-faq', ['items' => []]);
    }
}
