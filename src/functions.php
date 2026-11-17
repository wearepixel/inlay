<?php

declare(strict_types=1);

use WeArePixel\Inlay\SingleFile\Registration;
use WeArePixel\Inlay\SingleFile\Section;

if (! function_exists('section')) {
    /**
     * Declares a single-file Inlay section. Called from the PHP section at
     * the top of a Blade file registered via Inlay::singleFileSection()
     * or Inlay::singleFileSections() - see docs/inlay/v1/single-file-sections.
     */
    function section(Section $sectionType): void
    {
        Registration::register($sectionType);
    }
}
