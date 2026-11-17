<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('logo-cloud')
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
<section class="bg-body py-5">
    <div class="container">
        @if ($heading !== '')
            <p class="text-center small fw-semibold text-body-secondary">{{ $heading }}</p>
        @endif
        <ul class="list-unstyled d-flex flex-wrap align-items-center justify-content-center gap-5 mt-4 mb-0">
            @foreach ($logos as $logo)
                @if (($logo['logo'] ?? null)?->isEmpty() === false)
                    <li><img src="{{ $logo['logo'] }}" alt="{{ $logo['name'] ?? '' }}" height="32" class="object-fit-contain opacity-75"></li>
                @endif
            @endforeach
        </ul>
    </div>
</section>
