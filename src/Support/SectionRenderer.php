<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Support;

final class SectionRenderer
{
    public function __construct(private readonly SectionRegistry $registry) {}

    /**
     * @param iterable<array{section_type: string, props: array<string, mixed>}> $sections
     */
    public function render(iterable $sections): string
    {
        $html = '';

        foreach ($sections as $section) {
            $componentClass = $this->registry->resolve($section['section_type']);

            /** @var \Illuminate\View\Component $component */
            $component = new $componentClass(...$section['props']);

            $data = array_merge(
                get_object_vars($component),
                method_exists($component, 'with') ? $component->with() : [],
            );

            $html .= $component->render()->with($data)->render();
        }

        return $html;
    }
}
