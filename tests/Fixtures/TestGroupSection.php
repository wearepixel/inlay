<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Contracts\InlayComponent;
use WeArePixel\Inlay\Schema\Settings;

final class TestGroupSection extends Component implements InlayComponent
{
    public function __construct(
        public array $author = [],
    ) {}

    public static function settings(): Settings
    {
        $itemSchema = Settings::make()
            ->text('name')->label('Name')
            ->text('title')->label('Title');

        return Settings::make()->group('author', $itemSchema)->label('Author');
    }

    public function render(): View
    {
        return view('test-group');
    }
}
