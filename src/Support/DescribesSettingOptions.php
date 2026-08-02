<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

/**
 * Optional - implemented by a backed enum whose cases have a short
 * explanatory description worth showing under a Select field once that
 * option is chosen (e.g. "Large - full-width, best for a strong first
 * impression"). A separate interface from SettingOptions so that adding it
 * is never a breaking change for an enum that already implements
 * SettingOptions for its label.
 */
interface DescribesSettingOptions
{
    public function optionDescription(): ?string;
}
