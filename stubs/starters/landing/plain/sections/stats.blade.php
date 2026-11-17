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
<section class="inlay-section inlay-tone-{{ $tone === 'dark' ? 'dark' : 'muted' }} inlay-stats">
    <div class="inlay-container">
        @if ($heading !== '')
            <h2 class="inlay-heading inlay-center">{{ $heading }}</h2>
        @endif
        <dl class="inlay-stats__list">
            @foreach ($stats as $stat)
                <div class="inlay-stats__item">
                    <dt class="inlay-stats__label">{{ $stat['label'] ?? '' }}</dt>
                    <dd class="inlay-stats__value">{{ $stat['value'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
