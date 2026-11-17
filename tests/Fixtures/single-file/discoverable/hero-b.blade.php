<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('discoverable-b')
        ->name('Discoverable B')
        ->settings(Settings::make()->text('heading')->label('Heading')->default('B'))
);
?>
<div data-testid="discoverable-b">{{ $heading }}</div>
