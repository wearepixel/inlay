<?php

declare(strict_types=1);

use WeArePixel\Inlay\SingleFile\Component;
use WeArePixel\Inlay\SingleFile\Registration;

if (! function_exists('component')) {
    /**
     * Declares a single-file Inlay component. Called from the PHP block at
     * the top of a Blade file registered via Inlay::singleFileComponent()
     * or Inlay::singleFileComponents() - see docs/components.md.
     */
    function component(Component $component): void
    {
        Registration::register($component);
    }
}
