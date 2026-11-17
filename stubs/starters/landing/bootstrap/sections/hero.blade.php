<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;
use WeArePixel\Inlay\Support\LinkValue;

section(
    Section::make('hero')
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
    $toneClasses = ['light' => 'bg-body', 'muted' => 'bg-body-tertiary', 'dark' => 'bg-dark text-white'][$tone] ?? 'bg-body';
    $centered = $layout === 'centered' || $image->isEmpty();
    $outline = $tone === 'dark' ? 'btn-outline-light' : 'btn-outline-secondary';
@endphp
<section class="{{ $toneClasses }}">
    <div class="container py-5">
        <div class="row g-5 py-lg-5 {{ $centered ? 'justify-content-center text-center' : 'align-items-center' }}">
            <div class="{{ $centered ? 'col-lg-9' : 'col-lg-6' }}">
                @if ($eyebrow !== '')
                    <p class="text-uppercase small fw-semibold opacity-75 mb-3">{{ $eyebrow }}</p>
                @endif
                <h1 class="display-4 fw-bold">{{ $heading }}</h1>
                @if ($body !== '')
                    <p class="lead mt-4 opacity-75">{{ $body }}</p>
                @endif
                @if ($primaryCta->url !== '' || $secondaryCta->url !== '')
                    <div class="d-flex flex-wrap gap-3 mt-5 {{ $centered ? 'justify-content-center' : '' }}">
                        @if ($primaryCta->url !== '')
                            <a href="{{ $primaryCta->url }}" @if ($primaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="btn btn-primary btn-lg">{{ $primaryCta->label }}</a>
                        @endif
                        @if ($secondaryCta->url !== '')
                            <a href="{{ $secondaryCta->url }}" @if ($secondaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="btn btn-lg {{ $outline }}">{{ $secondaryCta->label }}</a>
                        @endif
                    </div>
                @endif
            </div>
            @unless ($image->isEmpty())
                <div class="{{ $centered ? 'col-lg-10' : 'col-lg-6' }}">
                    <img src="{{ $image }}" alt="{{ $imageAlt }}" class="img-fluid w-100 rounded-4 shadow-lg object-fit-cover">
                </div>
            @endunless
        </div>
    </div>
</section>
