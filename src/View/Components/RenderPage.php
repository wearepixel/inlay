<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Models\Page;

final class RenderPage extends Component
{
    private Htmlable $html;

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(Page $page, InlayManager $inlay, ?array $context = null)
    {
        $this->html = $inlay->renderPage($page, $context);
    }

    public function render(): Htmlable
    {
        return $this->html;
    }
}
