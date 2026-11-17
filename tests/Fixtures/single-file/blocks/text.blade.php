<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('text')
        ->name('Text')
        ->settings(Settings::make()->richtext('body')->label('Body')->default('<p>Some text</p>'))
);
?>
<div class="text">{!! $body !!}</div>
