<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\BladePageBuilder\Contracts\Sectionable;
use WearePixel\BladePageBuilder\Schema\Schema;

final class TestFaqSection extends Component implements Sectionable
{
    public function __construct(
        public array $items = [],
    ) {}

    public static function schema(): Schema
    {
        $itemSchema = Schema::make()
            ->text('question', label: 'Question')
            ->textarea('answer', label: 'Answer');

        return Schema::make()->repeater('items', label: 'FAQ items', itemSchema: $itemSchema);
    }

    public function render(): View
    {
        return view('test-faq');
    }
}
