<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

// Deliberately omits ->default() - used to test the "invalid Settings
// definition" registration error, since a single-file section has no
// constructor to fall back on.
section(
    Section::make('single-file-no-default')
        ->settings(Settings::make()->text('heading')->label('Heading'))
);
?>
<div>{{ $heading }}</div>
