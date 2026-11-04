<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('broken')
        ->name('Broken')
        ->settings(Settings::make()->text('heading')->default('Hi'))
);
?>
<section>{{ $undefinedVariable->explode() }}</section>
