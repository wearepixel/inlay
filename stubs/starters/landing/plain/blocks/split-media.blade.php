<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;
use WeArePixel\Inlay\Support\LinkValue;

block(
    Block::make('split-media')
        ->name('Text and image')
        ->category('Content')
        ->description('Half text, half image, side by side (stacked on mobile). The text side has a heading, a paragraph of rich text, an optional checklist of short points and a button. Alternate image position between consecutive uses.')
        ->keywords(['image and text', 'media', 'two column', 'about', 'how it works', 'checklist'])
        ->settings(
            Settings::make()
                ->text('eyebrow')->label('Eyebrow')->maxLength(60)->default('')
                ->text('heading')->label('Heading')->required()->maxLength(90)->default('Tell the story behind the product')
                ->richtext('body')->label('Body')->default('<p>Explain how it works or why it is different, in two or three sentences.</p>')
                ->repeater('points', Settings::make()->text('text')->label('Point')->required()->maxLength(120))->label('Checklist')->default([])
                ->link('cta')->label('Button')->default(new LinkValue)
                ->image('image')->label('Image')->default('')
                ->text('imageAlt')->label('Image description')->default('')
                ->select('imagePosition')->label('Image position')->options(['right' => 'Right', 'left' => 'Left'])->default('right')
        )
);
?>
@php($hasImage = ! $image->isEmpty())
<section class="inlay-section inlay-split {{ $hasImage ? 'inlay-split--with-image' : '' }} {{ $imagePosition === 'left' ? 'inlay-split--image-left' : '' }}">
    <div class="inlay-container inlay-split__inner">
        <div class="inlay-split__content">
            @if ($eyebrow !== '')
                <p class="inlay-eyebrow">{{ $eyebrow }}</p>
            @endif
            <h2 class="inlay-heading">{{ $heading }}</h2>
            <div class="inlay-prose">{!! app(\WeArePixel\Inlay\Support\RichTextSanitizer::class)->sanitize($body) !!}</div>
            @if ($points !== [])
                <ul class="inlay-checklist">
                    @foreach ($points as $point)
                        <li>{{ $point['text'] ?? '' }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($cta->url !== '')
                <div class="inlay-actions">
                    <a href="{{ $cta->url }}" @if ($cta->openInNewTab) target="_blank" rel="noopener" @endif class="inlay-button">{{ $cta->label }}</a>
                </div>
            @endif
        </div>
        @if ($hasImage)
            <img src="{{ $image }}" alt="{{ $imageAlt }}" class="inlay-split__image">
        @endif
    </div>
</section>
