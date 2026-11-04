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
<section class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-6">
        @if ($heading !== '')
            <p class="text-center text-sm font-semibold text-gray-600">{{ $heading }}</p>
        @endif
        <ul class="mt-8 flex flex-wrap items-center justify-center gap-x-12 gap-y-8">
            @foreach ($logos as $logo)
                @if (($logo['logo'] ?? null)?->isEmpty() === false)
                    <li><img src="{{ $logo['logo'] }}" alt="{{ $logo['name'] ?? '' }}" class="h-8 w-auto object-contain opacity-70 grayscale"></li>
                @endif
            @endforeach
        </ul>
    </div>
</section>
