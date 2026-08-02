<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Component;

component(
    Component::make('single-file-nested-livewire')
        ->name('Nested Livewire')
        ->settings(Settings::make()->text('heading')->label('Heading')->default('Widget'))
);
?>
<div data-testid="single-file-nested-livewire">
    <h2>{{ $heading }}</h2>
    <livewire:spike-inline-widget />
</div>
