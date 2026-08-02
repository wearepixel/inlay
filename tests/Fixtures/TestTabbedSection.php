<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Contracts\InlayComponent;
use WeArePixel\Inlay\Schema\Settings;

final class TestTabbedSection extends Component implements InlayComponent
{
    public function __construct(
        public string $heading = 'Welcome',
        public string $alignment = 'left',
        public string $metaTitle = '',
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()
            ->tab('Content', function (Settings $settings) {
                $settings->section('Hero', function (Settings $settings) {
                    $settings->text('heading')->label('Heading')->required();
                });
            })
            ->tab('Appearance', function (Settings $settings) {
                $settings->text('alignment')->label('Alignment')->badge('New');
            })
            ->tab('SEO', function (Settings $settings) {
                $settings->text('metaTitle')->label('Meta title');
            });
    }

    public function render(): View
    {
        return view('test-tabbed');
    }
}
