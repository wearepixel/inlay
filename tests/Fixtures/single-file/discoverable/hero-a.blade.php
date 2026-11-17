<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('discoverable-a')
        ->name('Discoverable A')
        ->settings(Settings::make()->text('heading')->label('Heading')->default('A'))
);
?>
<div data-testid="discoverable-a">{{ $heading }}</div>
