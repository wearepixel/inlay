<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('feature-grid')
        ->name('Feature grid')
        ->category('Content')
        ->description('A heading and intro over a grid of 2 to 4 columns of short features or benefits, each with a title, a sentence or two and an optional small image or icon.')
        ->keywords(['features', 'benefits', 'services', 'columns', 'cards', 'why us'])
        ->settings(
            Settings::make()
                ->text('eyebrow')->label('Eyebrow')->maxLength(60)->default('')
                ->text('heading')->label('Heading')->maxLength(90)->default('Everything you need')
                ->textarea('intro')->label('Intro')->maxLength(300)->default('')
                ->select('columns')->label('Columns')->options(['2' => '2', '3' => '3', '4' => '4'])->default('3')
                ->repeater('features', Settings::make()
                    ->image('icon')->label('Icon or image')
                    ->text('title')->label('Title')->required()->maxLength(60)
                    ->textarea('body')->label('Description')->maxLength(240))
                ->label('Features')->default([
                    ['title' => 'Fast', 'body' => 'Explain the first benefit in a sentence.'],
                    ['title' => 'Simple', 'body' => 'Explain the second benefit in a sentence.'],
                    ['title' => 'Reliable', 'body' => 'Explain the third benefit in a sentence.'],
                ])
        )
);
?>
@php
    $columnClasses = ['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-2 lg:grid-cols-3', '4' => 'sm:grid-cols-2 lg:grid-cols-4'][$columns] ?? 'sm:grid-cols-2 lg:grid-cols-3';
@endphp
<section class="bg-white py-20">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            @if ($eyebrow !== '')
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">{{ $eyebrow }}</p>
            @endif
            @if ($heading !== '')
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-gray-900 text-balance sm:text-4xl">{{ $heading }}</h2>
            @endif
            @if ($intro !== '')
                <p class="mt-4 text-lg text-gray-600 text-pretty">{{ $intro }}</p>
            @endif
        </div>
        <div class="mt-14 grid gap-10 {{ $columnClasses }}">
            @foreach ($features as $feature)
                <div>
                    @if (($feature['icon'] ?? null)?->isEmpty() === false)
                        <img src="{{ $feature['icon'] }}" alt="" class="mb-4 h-12 w-12 object-contain">
                    @endif
                    <h3 class="text-lg font-semibold text-gray-900">{{ $feature['title'] ?? '' }}</h3>
                    @if (($feature['body'] ?? '') !== '')
                        <p class="mt-2 text-gray-600">{{ $feature['body'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
