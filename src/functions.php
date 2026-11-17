<?php

declare(strict_types=1);

use WeArePixel\Inlay\SingleFile\Block;
use WeArePixel\Inlay\SingleFile\Registration;
use WeArePixel\Inlay\SingleFile\Section;

if (! function_exists('section')) {
    /**
     * Declares a single-file Inlay section. Called from the PHP block at
     * the top of a Blade file registered via Inlay::singleFileSection()
     * or Inlay::singleFileSections() - see docs/inlay/v1/single-file-sections.
     */
    function section(Section $sectionType): void
    {
        Registration::register($sectionType);
    }
}

if (! function_exists('block')) {
    /**
     * Declares a single-file Inlay block - a piece editors place inside a
     * section's blocks area. Registered via Inlay::singleFileBlocks().
     */
    function block(Block $block): void
    {
        Registration::register($block);
    }
}
