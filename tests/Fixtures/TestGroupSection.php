<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\BladePageBuilder\Contracts\Sectionable;
use WearePixel\BladePageBuilder\Schema\Schema;

final class TestGroupSection extends Component implements Sectionable
{
    public function __construct(
        public array $author = [],
    ) {}

    public static function schema(): Schema
    {
        $itemSchema = Schema::make()
            ->text('name', label: 'Name')
            ->text('title', label: 'Title');

        return Schema::make()->group('author', label: 'Author', itemSchema: $itemSchema);
    }

    public function render(): View
    {
        return view('test-group');
    }
}
