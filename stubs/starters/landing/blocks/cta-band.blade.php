<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;
use WeArePixel\Inlay\Support\LinkValue;

block(
    Block::make('cta-band')
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
    $toneClasses = ['brand' => 'bg-indigo-600 text-white', 'dark' => 'bg-gray-950 text-white', 'light' => 'bg-gray-50 text-gray-900'][$tone] ?? 'bg-indigo-600 text-white';
@endphp
<section class="{{ $toneClasses }}">
    <div class="mx-auto max-w-4xl px-6 py-16 text-center lg:py-20">
        <h2 class="text-3xl font-bold tracking-tight text-balance sm:text-4xl">{{ $heading }}</h2>
        @if ($body !== '')
            <p class="mt-4 text-lg opacity-80 text-pretty">{{ $body }}</p>
        @endif
        <div class="mt-8 flex flex-wrap justify-center gap-4">
            @if ($primaryCta->url !== '')
                <a href="{{ $primaryCta->url }}" @if ($primaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="rounded-md bg-white px-5 py-3 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-100">{{ $primaryCta->label }}</a>
            @endif
            @if ($secondaryCta->url !== '')
                <a href="{{ $secondaryCta->url }}" @if ($secondaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="rounded-md px-5 py-3 text-sm font-semibold ring-1 ring-current/30 hover:ring-current/60">{{ $secondaryCta->label }}</a>
            @endif
        </div>
    </div>
</section>
