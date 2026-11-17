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
<section class="{{ $tone === 'dark' ? 'bg-gray-950 text-white' : 'bg-gray-50 text-gray-900' }} py-16">
    <div class="mx-auto max-w-7xl px-6">
        @if ($heading !== '')
            <h2 class="text-center text-2xl font-bold tracking-tight text-balance">{{ $heading }}</h2>
        @endif
        <dl class="mt-10 grid grid-cols-2 gap-8 text-center lg:grid-flow-col lg:auto-cols-fr lg:grid-cols-none">
            @foreach ($stats as $stat)
                <div class="flex flex-col-reverse gap-2">
                    <dt class="text-sm opacity-70">{{ $stat['label'] ?? '' }}</dt>
                    <dd class="text-4xl font-bold tracking-tight">{{ $stat['value'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
