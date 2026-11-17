<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Import;

final readonly class ImportPlan
{
    /**
     * @param  array<int, ImportedSection>  $sections
     * @param  array<int, string>  $textBetweenSections  Blade or text outside the top-level elements, which the import leaves out
     */
    public function __construct(
        public array $sections,
        public array $textBetweenSections,
    ) {}
}
