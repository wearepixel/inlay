<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

/**
 * Optional - only needed when a backed enum's case names don't produce a
 * sensible label on their own. An enum that doesn't implement this gets a
 * convention-derived label from its case name instead.
 */
interface SettingOptions
{
    public function label(): string;
}
