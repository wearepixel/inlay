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
<section class="bg-white py-20">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-6 {{ $image->isEmpty() ? 'max-w-3xl' : 'lg:grid-cols-2' }}">
        <div class="{{ $imagePosition === 'left' ? 'lg:order-2' : '' }}">
            @if ($eyebrow !== '')
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">{{ $eyebrow }}</p>
            @endif
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-gray-900 text-balance sm:text-4xl">{{ $heading }}</h2>
            <div class="mt-6 space-y-4 text-lg text-gray-600 [&_a]:text-indigo-600 [&_a]:underline">{!! app(\WeArePixel\Inlay\Support\RichTextSanitizer::class)->sanitize($body) !!}</div>
            @if ($points !== [])
                <ul class="mt-8 space-y-3">
                    @foreach ($points as $point)
                        <li class="flex gap-3 text-gray-700"><span aria-hidden="true" class="text-indigo-600">&#10003;</span>{{ $point['text'] ?? '' }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($cta->url !== '')
                <a href="{{ $cta->url }}" @if ($cta->openInNewTab) target="_blank" rel="noopener" @endif class="mt-10 inline-block rounded-md bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ $cta->label }}</a>
            @endif
        </div>
        @unless ($image->isEmpty())
            <img src="{{ $image }}" alt="{{ $imageAlt }}" class="w-full rounded-2xl object-cover shadow-lg {{ $imagePosition === 'left' ? 'lg:order-1' : '' }}">
        @endunless
    </div>
</section>
