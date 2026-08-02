<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Attributes\BlockMeta;
use WeArePixel\Inlay\Contracts\InlayBlock;
use WeArePixel\Inlay\Schema\Settings;

/**
 * A block-picker/editor fixture used by the browser test suite: a plain
 * text setting plus a toggle-gated conditional setting, so the workflow
 * test can exercise editing a text field and verifying visibleWhen()
 * dependent-field visibility end to end in a real browser.
 */
#[BlockMeta(label: 'Hero', category: 'Headers', description: 'A hero banner with an optional call to action.')]
final class BrowserHeroSection extends Component implements InlayBlock
{
    public function __construct(
        public string $heading = 'Welcome',
        public bool $showCta = false,
        public string $ctaLabel = 'Learn more',
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()
            ->text('heading')->label('Heading')
            ->toggle('showCta')->label('Show call to action')
            ->text('ctaLabel')->label('Call to action label')->visibleWhen('showCta', true);
    }

    public function render(): View
    {
        return view('browser-hero');
    }
}
