<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('default-image')
        ->settings(
            Settings::make()
                ->image('photo')->label('Photo')->default('')
                ->link('cta')->label('Button')->default(['label' => 'Go', 'url' => '/go'])
        )
);
?>
<p>{{ $photo->isEmpty() ? 'no-photo' : $photo->path }}|{{ $cta->url }}</p>
