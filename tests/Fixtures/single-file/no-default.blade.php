<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

// Deliberately omits ->default() - used to test the "invalid Settings
// definition" registration error, since a single-file block has no
// constructor to fall back on.
block(
    Block::make('single-file-no-default')
        ->settings(Settings::make()->text('heading')->label('Heading'))
);
?>
<div>{{ $heading }}</div>
