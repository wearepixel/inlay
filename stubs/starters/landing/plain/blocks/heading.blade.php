<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('heading')
        ->name('Heading')
        ->description('A title or subheading.')
        ->settings(
            Settings::make()
                ->text('text')->label('Text')->required()->maxLength(120)->default('A heading')
                ->select('level')->label('Size')->options(['h2' => 'Large', 'h3' => 'Medium', 'h4' => 'Small'])->default('h2')
        )
);
?>
@php($size = "inlay-heading inlay-heading--{$level}")
<{{ $level }} class="{{ $size }}">{{ $text }}</{{ $level }}>
