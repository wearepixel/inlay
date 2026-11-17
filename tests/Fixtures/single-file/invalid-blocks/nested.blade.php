<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('nested')
        ->settings(Settings::make()->blocks('inner')->default([]))
);
?>
<div>{{ $inner }}</div>
