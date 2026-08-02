<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Component;

// Deliberately omits ->default() - used to test the "invalid Settings
// definition" registration error, since a single-file component has no
// constructor to fall back on.
component(
    Component::make('single-file-no-default')
        ->settings(Settings::make()->text('heading')->label('Heading'))
);
?>
<div>{{ $heading }}</div>
