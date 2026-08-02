<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Component;
use WeArePixel\Inlay\Tests\Fixtures\SingleFile\SingleFileImageSize;

component(
    Component::make('single-file-hero')
        ->name('Hero')
        ->category('Headers')
        ->description('A single-file hero component.')
        ->icon('layout-header')
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->default('Welcome')
                ->toggle('showCta')->label('Show call to action')->default(false)
                ->text('ctaLabel')->label('Call to action label')->default('Learn more')->visibleWhen('showCta', true)
                ->select('imageSize')->label('Image size')->options(SingleFileImageSize::class)->default(SingleFileImageSize::Large)
        )
);
?>
<section data-testid="single-file-hero">
    <h1>{{ $heading }}</h1>

    @if ($showCta)
        <a href="#" data-testid="single-file-hero-cta">{{ $ctaLabel }}</a>
    @endif

    <span data-testid="single-file-hero-size">{{ $imageSize->value }}</span>
</section>
