<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('image')
        ->name('Image')
        ->description('A photo or illustration with an optional caption.')
        ->settings(
            Settings::make()
                ->image('image')->label('Image')->default('')
                ->text('alt')->label('Image description')->help('Describe the image for screen readers.')->default('')
                ->text('caption')->label('Caption')->maxLength(200)->default('')
        )
);
?>
@unless ($image->isEmpty())
    <figure class="inlay-figure">
        <img src="{{ $image }}" alt="{{ $alt }}" class="inlay-figure__image">
        @if ($caption !== '')
            <figcaption class="inlay-figure__caption">{{ $caption }}</figcaption>
        @endif
    </figure>
@endunless
