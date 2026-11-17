<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Attributes\SectionMeta;
use WeArePixel\Inlay\Contracts\InlaySection;
use WeArePixel\Inlay\Schema\Settings;

#[SectionMeta(label: 'Quote', category: 'Content', description: 'A quotation.')]
final class BrowserQuoteSection extends Component implements InlaySection
{
    public function __construct(
        public string $quote = 'Quote text',
        public string $author = 'Author name',
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()
            ->text('quote')->label('Quote')
            ->text('author')->label('Author');
    }

    public function render(): View
    {
        return view('browser-hero', ['heading' => $this->quote]);
    }
}
