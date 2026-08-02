<?php

use WearePixel\Inlay\Schema\Settings;
use WearePixel\Inlay\SingleFile\Component;

component(
    Component::make('discoverable-b')
        ->name('Discoverable B')
        ->settings(Settings::make()->text('heading')->label('Heading')->default('B'))
);
?>
<div data-testid="discoverable-b">{{ $heading }}</div>
