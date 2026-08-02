<?php

declare(strict_types=1);

namespace WearePixel\Inlay\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Support\SectionRenderer;

final class RenderPage extends Component
{
    private HtmlString $html;

    public function __construct(Page $page, SectionRenderer $renderer)
    {
        $revision = $page->revisions()->first();

        $sections = $revision ? $revision->snapshot : [];

        $this->html = new HtmlString($renderer->render($sections));
    }

    public function render(): Htmlable
    {
        return $this->html;
    }
}
