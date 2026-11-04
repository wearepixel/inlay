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
@php($split = $layout === 'split' && ! $image->isEmpty())
<section class="inlay-section inlay-tone-{{ $tone }} inlay-hero {{ $split ? 'inlay-hero--split' : 'inlay-hero--centered' }}">
    <div class="inlay-container inlay-hero__inner">
        <div class="inlay-hero__content">
            @if ($eyebrow !== '')
                <p class="inlay-eyebrow">{{ $eyebrow }}</p>
            @endif
            <h1 class="inlay-hero__title">{{ $heading }}</h1>
            @if ($body !== '')
                <p class="inlay-lead">{{ $body }}</p>
            @endif
            @if ($primaryCta->url !== '' || $secondaryCta->url !== '')
                <div class="inlay-actions">
                    @if ($primaryCta->url !== '')
                        <a href="{{ $primaryCta->url }}" @if ($primaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="inlay-button">{{ $primaryCta->label }}</a>
                    @endif
                    @if ($secondaryCta->url !== '')
                        <a href="{{ $secondaryCta->url }}" @if ($secondaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="inlay-button inlay-button--outline">{{ $secondaryCta->label }}</a>
                    @endif
                </div>
            @endif
        </div>
        @unless ($image->isEmpty())
            <img src="{{ $image }}" alt="{{ $imageAlt }}" class="inlay-hero__image">
        @endunless
    </div>
</section>
