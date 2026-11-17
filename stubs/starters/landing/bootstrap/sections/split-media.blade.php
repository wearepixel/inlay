<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;
use WeArePixel\Inlay\Support\LinkValue;

section(
    Section::make('split-media')
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
<section class="bg-body py-5">
    <div class="container py-lg-4">
        <div class="row g-5 align-items-center {{ $image->isEmpty() ? 'justify-content-center' : '' }}">
            <div class="{{ $image->isEmpty() ? 'col-lg-8' : 'col-lg-6' }} {{ $imagePosition === 'left' ? 'order-lg-2' : '' }}">
                @if ($eyebrow !== '')
                    <p class="text-uppercase small fw-semibold text-primary mb-2">{{ $eyebrow }}</p>
                @endif
                <h2 class="display-6 fw-bold">{{ $heading }}</h2>
                <div class="lead text-body-secondary mt-4">{!! app(\WeArePixel\Inlay\Support\RichTextSanitizer::class)->sanitize($body) !!}</div>
                @if ($points !== [])
                    <ul class="list-unstyled mt-4">
                        @foreach ($points as $point)
                            <li class="d-flex gap-2 mb-2"><span aria-hidden="true" class="text-primary">&#10003;</span>{{ $point['text'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($cta->url !== '')
                    <a href="{{ $cta->url }}" @if ($cta->openInNewTab) target="_blank" rel="noopener" @endif class="btn btn-primary btn-lg mt-4">{{ $cta->label }}</a>
                @endif
            </div>
            @unless ($image->isEmpty())
                <div class="col-lg-6 {{ $imagePosition === 'left' ? 'order-lg-1' : '' }}">
                    <img src="{{ $image }}" alt="{{ $imageAlt }}" class="img-fluid w-100 rounded-4 shadow object-fit-cover">
                </div>
            @endunless
        </div>
    </div>
</section>
