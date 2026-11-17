<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

// Single-file so the fixture needs no autoloadable PHP of its own.
section(
    Section::make('no-livewire-hero')
        ->name('Hero')
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->default('Welcome')
        )
);
?>
<section data-testid="no-livewire-hero">
    <h1>{{ $heading }}</h1>
</section>
