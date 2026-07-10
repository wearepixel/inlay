<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\BladePageBuilder\Contracts\Sectionable;
use WearePixel\BladePageBuilder\Schema\Schema;

final class TestCtaSection extends Component implements Sectionable
{
    /**
     * @param  array{label: string, url: string, new_tab: bool}  $cta
     */
    public function __construct(
        public array $cta = ['label' => '', 'url' => '', 'new_tab' => false],
    ) {}

    public static function schema(): Schema
    {
        return Schema::make()->link('cta', label: 'Call to action');
    }

    public function render(): View
    {
        return view('test-cta');
    }
}
