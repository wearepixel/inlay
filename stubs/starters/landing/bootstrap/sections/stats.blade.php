<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('stats')
        ->name('Stats')
        ->category('Social proof')
        ->description('A row of 2 to 4 large numbers with a short label under each, e.g. "10k+ customers". Use for proof points, not for prices.')
        ->keywords(['numbers', 'metrics', 'figures', 'results', 'counters'])
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->maxLength(90)->default('')
                ->repeater('stats', Settings::make()
                    ->text('value')->label('Number')->required()->maxLength(12)
                    ->text('label')->label('Label')->required()->maxLength(60))
                ->label('Stats')->default([
                    ['value' => '10k+', 'label' => 'Customers'],
                    ['value' => '4.9', 'label' => 'Average rating'],
                    ['value' => '24/7', 'label' => 'Support'],
                ])
                ->select('tone')->label('Background')->options(['light' => 'Light', 'dark' => 'Dark'])->default('light')
        )
);
?>
<section class="{{ $tone === 'dark' ? 'bg-dark text-white' : 'bg-body-tertiary' }} py-5">
    <div class="container">
        @if ($heading !== '')
            <h2 class="h3 fw-bold text-center">{{ $heading }}</h2>
        @endif
        <dl class="row row-cols-2 row-cols-lg-{{ max(1, min(4, count($stats))) }} g-4 text-center mt-3 mb-0">
            @foreach ($stats as $stat)
                <div class="col d-flex flex-column-reverse gap-1">
                    <dt class="small fw-normal opacity-75">{{ $stat['label'] ?? '' }}</dt>
                    <dd class="display-6 fw-bold mb-0">{{ $stat['value'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
