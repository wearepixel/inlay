<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('open')
        ->settings(Settings::make()->blocks('content')->default([]))
);
?>
<section>{{ $content }}</section>
