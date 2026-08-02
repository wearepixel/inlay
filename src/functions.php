<?php

declare(strict_types=1);

use WeArePixel\Inlay\SingleFile\Block;
use WeArePixel\Inlay\SingleFile\Registration;

if (! function_exists('block')) {
    /**
     * Declares a single-file Inlay block. Called from the PHP block at
     * the top of a Blade file registered via Inlay::singleFileBlock()
     * or Inlay::singleFileBlocks() - see docs/blocks.md.
     */
    function block(Block $block): void
    {
        Registration::register($block);
    }
}
