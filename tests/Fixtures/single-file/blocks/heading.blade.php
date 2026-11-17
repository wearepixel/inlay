<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('heading')
        ->name('Heading')
        ->settings(Settings::make()->text('text')->label('Text')->required()->default('A heading'))
);
?>
<h2>{{ $text }}</h2>
