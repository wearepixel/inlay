<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('feature-grid')
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
    $columnClasses = ['2' => 'row-cols-1 row-cols-sm-2', '3' => 'row-cols-1 row-cols-sm-2 row-cols-lg-3', '4' => 'row-cols-1 row-cols-sm-2 row-cols-lg-4'][$columns] ?? 'row-cols-1 row-cols-sm-2 row-cols-lg-3';
@endphp
<section class="bg-body py-5">
    <div class="container py-lg-4">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                @if ($eyebrow !== '')
                    <p class="text-uppercase small fw-semibold text-primary mb-2">{{ $eyebrow }}</p>
                @endif
                @if ($heading !== '')
                    <h2 class="display-6 fw-bold">{{ $heading }}</h2>
                @endif
                @if ($intro !== '')
                    <p class="lead text-body-secondary mt-3">{{ $intro }}</p>
                @endif
            </div>
        </div>
        <div class="row {{ $columnClasses }} g-5 mt-2">
            @foreach ($features as $feature)
                <div class="col">
                    @if (($feature['icon'] ?? null)?->isEmpty() === false)
                        <img src="{{ $feature['icon'] }}" alt="" width="48" height="48" class="mb-3 object-fit-contain">
                    @endif
                    <h3 class="h5 fw-semibold">{{ $feature['title'] ?? '' }}</h3>
                    @if (($feature['body'] ?? '') !== '')
                        <p class="text-body-secondary mb-0">{{ $feature['body'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
