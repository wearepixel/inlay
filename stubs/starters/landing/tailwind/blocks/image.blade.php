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
    <figure class="w-full">
        <img src="{{ $image }}" alt="{{ $alt }}" class="w-full rounded-2xl object-cover">
        @if ($caption !== '')
            <figcaption class="mt-3 text-sm opacity-70">{{ $caption }}</figcaption>
        @endif
    </figure>
@endunless
