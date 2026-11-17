<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;
use WeArePixel\Inlay\Support\LinkValue;

section(
    Section::make('layered-hero')
        ->name('Layered hero')
        ->category('Headers')
        ->description('An opening section with the headline, text and two buttons on one side and a layered image on the other: a main image with a small stat or quote card overlapping its corner, plus a row of short trust signals under the buttons. Use once, at the top.')
        ->keywords(['hero', 'banner', 'header', 'headline', 'above the fold', 'stat', 'social proof'])
        ->settings(
            Settings::make()
                ->tab('Content', fn (Settings $t) => $t
                    ->text('eyebrow')->label('Badge')->help('Short label above the headline.')->maxLength(60)->default('New: automated weekly reports')
                    ->text('heading')->label('Headline')->required()->maxLength(90)->default('Ship projects on time, without the status meetings')
                    ->textarea('body')->label('Supporting text')->maxLength(300)->default('Northwind keeps every task, file and decision in one place, so your team always knows what is next and clients always know where things stand.')
                    ->link('primaryCta')->label('Primary button')->default(new LinkValue('Start free trial', '#'))
                    ->link('secondaryCta')->label('Secondary button')->default(new LinkValue('Book a demo', '#')))
                ->tab('Media', fn (Settings $t) => $t
                    ->image('image')->label('Main image')->default('')
                    ->text('imageAlt')->label('Main image description')->help('Describe the image for screen readers.')->default('')
                    ->select('cardStyle')->label('Floating card')->options(['stat' => 'Stat', 'quote' => 'Quote', 'none' => 'Hidden'])->default('stat')
                    ->text('cardValue')->label('Card headline')->help('A number for a stat card, or the quote itself.')->maxLength(140)->default('38%')
                    ->text('cardLabel')->label('Card caption')->help('What the number means, or who said the quote.')->maxLength(80)->default('fewer missed deadlines in the first quarter'))
                ->tab('Trust', fn (Settings $t) => $t
                    ->repeater('trustItems', Settings::make()
                        ->text('text')->label('Text')->required()->maxLength(60))
                    ->label('Trust signals')->default([
                        ['text' => '4.9/5 from 2,000 reviews'],
                        ['text' => 'SOC 2 Type II'],
                        ['text' => 'No credit card required'],
                    ]))
        )
);
?>
@php
    $hasImage = ! $image->isEmpty();
    $showCard = $cardStyle !== 'none' && $cardValue !== '';
    $trustItems = array_values(array_filter($trustItems, fn (array $item) => ($item['text'] ?? '') !== ''));
@endphp
<section class="overflow-hidden bg-white text-gray-900">
    <div class="mx-auto grid max-w-7xl items-center gap-16 px-6 py-20 lg:grid-cols-2 lg:py-28">
        <div>
            @if ($eyebrow !== '')
                <p class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 ring-1 ring-indigo-600/20 ring-inset">
                    <span class="size-1.5 rounded-full bg-indigo-600" aria-hidden="true"></span>
                    {{ $eyebrow }}
                </p>
            @endif
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ $heading }}</h1>
            @if ($body !== '')
                <p class="mt-6 max-w-xl text-lg leading-8 text-gray-600 text-pretty">{{ $body }}</p>
            @endif
            @if ($primaryCta->url !== '' || $secondaryCta->url !== '')
                <div class="mt-10 flex flex-wrap gap-4">
                    @if ($primaryCta->url !== '')
                        <a href="{{ $primaryCta->url }}" @if ($primaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="rounded-md bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $primaryCta->label }}</a>
                    @endif
                    @if ($secondaryCta->url !== '')
                        <a href="{{ $secondaryCta->url }}" @if ($secondaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="rounded-md px-5 py-3 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 transition-colors hover:bg-gray-50 hover:ring-gray-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $secondaryCta->label }}</a>
                    @endif
                </div>
            @endif
            @if ($trustItems !== [])
                <ul class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm text-gray-600">
                    @foreach ($trustItems as $item)
                        <li class="flex items-center gap-2">
                            <svg class="size-4 shrink-0 text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                            {{ $item['text'] }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="relative pb-10 pl-6 sm:pl-10 lg:pb-14">
            <div class="absolute -top-6 -right-6 size-48 rounded-full bg-indigo-100 blur-3xl" aria-hidden="true"></div>
            @if ($hasImage)
                <img src="{{ $image }}" alt="{{ $imageAlt }}" class="relative aspect-4/3 w-full rounded-2xl object-cover shadow-2xl ring-1 ring-gray-900/10">
            @else
                <div class="relative flex aspect-4/3 w-full flex-col gap-4 rounded-2xl bg-linear-to-br from-indigo-600 to-violet-700 p-6 shadow-2xl sm:p-8" aria-hidden="true">
                    <div class="flex gap-1.5">
                        <span class="size-2.5 rounded-full bg-white/30"></span>
                        <span class="size-2.5 rounded-full bg-white/30"></span>
                        <span class="size-2.5 rounded-full bg-white/30"></span>
                    </div>
                    <div class="grid flex-1 grid-cols-3 gap-3">
                        <div class="space-y-3 rounded-lg bg-white/10 p-3">
                            <div class="h-2 w-2/3 rounded-full bg-white/40"></div>
                            <div class="h-10 rounded-md bg-white/20"></div>
                            <div class="h-10 rounded-md bg-white/20"></div>
                        </div>
                        <div class="space-y-3 rounded-lg bg-white/10 p-3">
                            <div class="h-2 w-1/2 rounded-full bg-white/40"></div>
                            <div class="h-10 rounded-md bg-white/20"></div>
                        </div>
                        <div class="space-y-3 rounded-lg bg-white/10 p-3">
                            <div class="h-2 w-3/4 rounded-full bg-white/40"></div>
                            <div class="h-10 rounded-md bg-white/20"></div>
                            <div class="h-10 rounded-md bg-white/20"></div>
                            <div class="h-10 rounded-md bg-white/20"></div>
                        </div>
                    </div>
                </div>
            @endif
            @if ($showCard)
                <figure class="absolute bottom-0 left-0 max-w-64 rounded-xl bg-white p-5 shadow-xl ring-1 ring-gray-900/5">
                    @if ($cardStyle === 'quote')
                        <blockquote class="text-sm leading-6 font-medium text-gray-900">&ldquo;{{ $cardValue }}&rdquo;</blockquote>
                        @if ($cardLabel !== '')
                            <figcaption class="mt-3 text-xs text-gray-500">{{ $cardLabel }}</figcaption>
                        @endif
                    @else
                        <p class="text-3xl font-bold tracking-tight text-indigo-600">{{ $cardValue }}</p>
                        @if ($cardLabel !== '')
                            <figcaption class="mt-1 text-sm text-gray-600">{{ $cardLabel }}</figcaption>
                        @endif
                    @endif
                </figure>
            @endif
        </div>
    </div>
</section>
