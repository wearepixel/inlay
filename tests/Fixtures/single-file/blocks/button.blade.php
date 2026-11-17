<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;
use WeArePixel\Inlay\Support\LinkValue;

block(
    Block::make('button')
        ->name('Button')
        ->settings(Settings::make()->link('link')->label('Link')->default(new LinkValue('Go', '/go')))
);
?>
<a href="{{ $link->url }}">{{ $link->label }}</a>
