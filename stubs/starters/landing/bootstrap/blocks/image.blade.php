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
    <figure class="figure w-100 mb-0">
        <img src="{{ $image }}" alt="{{ $alt }}" class="figure-img img-fluid w-100 rounded-4 object-fit-cover">
        @if ($caption !== '')
            <figcaption class="figure-caption">{{ $caption }}</figcaption>
        @endif
    </figure>
@endunless
