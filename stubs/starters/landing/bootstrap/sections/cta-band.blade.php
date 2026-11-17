<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;
use WeArePixel\Inlay\Support\LinkValue;

section(
    Section::make('cta-band')
        ->name('Call to action')
        ->category('Conversion')
        ->description('A full-width coloured band with a short heading, one line of text and up to two buttons. Use to close a page or break up long content - not at the very top (use Hero there).')
        ->keywords(['cta', 'banner', 'sign up', 'get started', 'closing'])
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->required()->maxLength(90)->default('Ready to get started?')
                ->textarea('body')->label('Text')->maxLength(200)->default('')
                ->link('primaryCta')->label('Primary button')->default(new LinkValue('Get started', '#'))
                ->link('secondaryCta')->label('Secondary button')->default(new LinkValue)
                ->select('tone')->label('Background')->options(['brand' => 'Brand colour', 'dark' => 'Dark', 'light' => 'Light'])->default('brand')
        )
);
?>
@php
    $toneClasses = ['brand' => 'bg-primary text-white', 'dark' => 'bg-dark text-white', 'light' => 'bg-body-tertiary'][$tone] ?? 'bg-primary text-white';
    $primary = $tone === 'light' ? 'btn-primary' : 'btn-light';
    $outline = $tone === 'light' ? 'btn-outline-secondary' : 'btn-outline-light';
@endphp
<section class="{{ $toneClasses }}">
    <div class="container py-5 text-center">
        <div class="row justify-content-center py-lg-3">
            <div class="col-lg-8">
                <h2 class="display-6 fw-bold">{{ $heading }}</h2>
                @if ($body !== '')
                    <p class="lead mt-3 mb-0 opacity-75">{{ $body }}</p>
                @endif
                @if ($primaryCta->url !== '' || $secondaryCta->url !== '')
                    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                        @if ($primaryCta->url !== '')
                            <a href="{{ $primaryCta->url }}" @if ($primaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="btn btn-lg {{ $primary }}">{{ $primaryCta->label }}</a>
                        @endif
                        @if ($secondaryCta->url !== '')
                            <a href="{{ $secondaryCta->url }}" @if ($secondaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="btn btn-lg {{ $outline }}">{{ $secondaryCta->label }}</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
