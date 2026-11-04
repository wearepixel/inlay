<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;
use WeArePixel\Inlay\Support\LinkValue;

block(
    Block::make('hero')
        ->name('Hero')
        ->category('Headers')
        ->description('The opening section of a landing page: a large headline, a short supporting paragraph, up to two buttons and an optional image beside or below the text. Use once, at the top.')
        ->keywords(['banner', 'header', 'headline', 'above the fold'])
        ->settings(
            Settings::make()
                ->text('eyebrow')->label('Eyebrow')->help('Short label above the headline.')->maxLength(60)->default('')
                ->text('heading')->label('Headline')->required()->maxLength(90)->default('A headline that says what you offer')
                ->textarea('body')->label('Supporting text')->maxLength(300)->default('One or two sentences on who it is for and why it matters.')
                ->link('primaryCta')->label('Primary button')->default(new LinkValue('Get started', '#'))
                ->link('secondaryCta')->label('Secondary button')->default(new LinkValue)
                ->image('image')->label('Image')->default('')
                ->text('imageAlt')->label('Image description')->help('Describe the image for screen readers.')->default('')
                ->select('layout')->label('Layout')->options(['split' => 'Text beside image', 'centered' => 'Centred, image below'])->default('split')
                ->select('tone')->label('Background')->options(['light' => 'Light', 'muted' => 'Muted', 'dark' => 'Dark'])->default('light')
        )
);
?>
@php
    $toneClasses = ['light' => 'bg-white text-gray-900', 'muted' => 'bg-gray-50 text-gray-900', 'dark' => 'bg-gray-950 text-white'][$tone] ?? 'bg-white text-gray-900';
    $centered = $layout === 'centered' || $image->isEmpty();
    $outline = $tone === 'dark' ? 'ring-white/40 hover:ring-white/70' : 'ring-gray-300 hover:ring-gray-400';
@endphp
<section class="{{ $toneClasses }}">
    <div class="mx-auto grid max-w-7xl gap-12 px-6 py-20 lg:py-28 {{ $centered ? 'text-center' : 'lg:grid-cols-2 lg:items-center' }}">
        <div class="{{ $centered ? 'mx-auto max-w-3xl' : '' }}">
            @if ($eyebrow !== '')
                <p class="text-sm font-semibold uppercase tracking-wider opacity-70">{{ $eyebrow }}</p>
            @endif
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-balance sm:text-6xl">{{ $heading }}</h1>
            @if ($body !== '')
                <p class="mt-6 text-lg leading-8 opacity-80 text-pretty">{{ $body }}</p>
            @endif
            @if ($primaryCta->url !== '' || $secondaryCta->url !== '')
                <div class="mt-10 flex flex-wrap gap-4 {{ $centered ? 'justify-center' : '' }}">
                    @if ($primaryCta->url !== '')
                        <a href="{{ $primaryCta->url }}" @if ($primaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="rounded-md bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ $primaryCta->label }}</a>
                    @endif
                    @if ($secondaryCta->url !== '')
                        <a href="{{ $secondaryCta->url }}" @if ($secondaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="rounded-md px-5 py-3 text-sm font-semibold ring-1 {{ $outline }}">{{ $secondaryCta->label }}</a>
                    @endif
                </div>
            @endif
        </div>
        @unless ($image->isEmpty())
            <img src="{{ $image }}" alt="{{ $imageAlt }}" class="w-full rounded-2xl object-cover shadow-xl {{ $centered ? 'mx-auto max-w-5xl' : '' }}">
        @endunless
    </div>
</section>
