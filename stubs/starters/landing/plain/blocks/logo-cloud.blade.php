<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('logo-cloud')
        ->name('Logo cloud')
        ->category('Social proof')
        ->description('A single row of partner, client or press logos with an optional one-line heading such as "Trusted by". Logos are shown greyscale at a consistent height.')
        ->keywords(['logos', 'clients', 'partners', 'as seen in', 'press'])
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->maxLength(80)->default('Trusted by teams at')
                ->repeater('logos', Settings::make()
                    ->image('logo')->label('Logo')->required()
                    ->text('name')->label('Company name')->help('Used as the logo description.')->required())
                ->label('Logos')->default([])
        )
);
?>
<section class="inlay-section inlay-logo-cloud">
    <div class="inlay-container">
        @if ($heading !== '')
            <p class="inlay-logo-cloud__heading">{{ $heading }}</p>
        @endif
        <ul class="inlay-logo-cloud__list">
            @foreach ($logos as $logo)
                @if (($logo['logo'] ?? null)?->isEmpty() === false)
                    <li><img src="{{ $logo['logo'] }}" alt="{{ $logo['name'] ?? '' }}" class="inlay-logo-cloud__logo"></li>
                @endif
            @endforeach
        </ul>
    </div>
</section>
