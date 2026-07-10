<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;
use Throwable;

final class SectionRenderer
{
    public function __construct(private readonly SectionRegistry $registry) {}

    /**
     * @param  iterable<array{section_type: string, props: array<string, mixed>}>  $sections
     */
    public function render(iterable $sections): string
    {
        $html = '';

        foreach ($sections as $section) {
            try {
                $componentClass = $this->registry->resolve($section['section_type']);

                /** @var Component $component */
                $component = new $componentClass(...$section['props']);

                $data = array_merge(
                    get_object_vars($component),
                    method_exists($component, 'with') ? $component->with() : [],
                );

                $html .= $component->render()->with($data)->render();
            } catch (Throwable $exception) {
                // A malformed section (e.g. props that bypassed PageEditor's
                // validation via a direct write such as RestorePageRevision)
                // must not take down every other section on the page.
                Log::warning('blade-page-builder: skipped a section that failed to render', [
                    'section_type' => $section['section_type'] ?? null,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $html;
    }
}
