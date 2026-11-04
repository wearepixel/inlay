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
<section class="inlay-section inlay-feature-grid">
    <div class="inlay-container">
        @if ($eyebrow !== '' || $heading !== '' || $intro !== '')
            <div class="inlay-section__header">
                @if ($eyebrow !== '')
                    <p class="inlay-eyebrow">{{ $eyebrow }}</p>
                @endif
                @if ($heading !== '')
                    <h2 class="inlay-heading">{{ $heading }}</h2>
                @endif
                @if ($intro !== '')
                    <p class="inlay-lead">{{ $intro }}</p>
                @endif
            </div>
        @endif
        <div class="inlay-grid inlay-grid--{{ $columns }}">
            @foreach ($features as $feature)
                <div class="inlay-feature">
                    @if (($feature['icon'] ?? null)?->isEmpty() === false)
                        <img src="{{ $feature['icon'] }}" alt="" class="inlay-feature__icon">
                    @endif
                    <h3 class="inlay-feature__title">{{ $feature['title'] ?? '' }}</h3>
                    @if (($feature['body'] ?? '') !== '')
                        <p class="inlay-muted">{{ $feature['body'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
