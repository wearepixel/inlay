<?php

use WearePixel\Inlay\Schema\Settings;
use WearePixel\Inlay\SingleFile\Component;

component(
    Component::make('discoverable-a')
        ->name('Discoverable A')
        ->settings(Settings::make()->text('heading')->label('Heading')->default('A'))
);
?>
<div data-testid="discoverable-a">{{ $heading }}</div>
