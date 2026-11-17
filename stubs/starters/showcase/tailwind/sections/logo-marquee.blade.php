<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('logo-marquee')
        ->name('Logo marquee')
        ->category('Social proof')
        ->description('A one-line heading such as "Trusted by teams at" over a continuously scrolling row of client or partner logos with faded edges. Logos without an image show the company name as text. Scrolling pauses on hover and stops for visitors who prefer reduced motion.')
        ->keywords(['logos', 'clients', 'partners', 'marquee', 'carousel', 'ticker', 'trusted by', 'as seen in'])
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->maxLength(80)->default('Trusted by teams at')
                ->select('speed')->label('Speed')->options(['slow' => 'Slow', 'normal' => 'Normal', 'fast' => 'Fast'])->default('normal')
                ->repeater('logos', Settings::make()
                    ->image('logo')->label('Logo')
                    ->text('name')->label('Company name')->help('Shown when there is no logo, and used as the logo description.')->required()->maxLength(40))
                ->label('Logos')->default([
                    ['name' => 'Acme Studio'],
                    ['name' => 'Brightline'],
                    ['name' => 'Copperleaf'],
                    ['name' => 'Fieldnote'],
                    ['name' => 'Harbour & Co'],
                    ['name' => 'Lumen Labs'],
                    ['name' => 'Northpeak'],
                    ['name' => 'Quarry'],
                ])
        )
);
?>
@php
    $durationClass = ['slow' => '[--marquee-duration:60s]', 'normal' => '[--marquee-duration:40s]', 'fast' => '[--marquee-duration:24s]'][$speed] ?? '[--marquee-duration:40s]';
    $logos = array_values(array_filter($logos, fn (array $logo) => ($logo['name'] ?? '') !== '' || (($logo['logo'] ?? null) instanceof \WeArePixel\Inlay\Support\ImageValue && ! $logo['logo']->isEmpty())));
@endphp
<section class="bg-white py-16">
    <style>
        @keyframes inlay-logo-marquee { to { transform: translateX(-50%); } }
        .inlay-logo-marquee { animation: inlay-logo-marquee var(--marquee-duration) linear infinite; }
        .inlay-logo-marquee:hover { animation-play-state: paused; }
        @media (prefers-reduced-motion: reduce) { .inlay-logo-marquee { animation: none; } }
    </style>
    <div class="mx-auto max-w-7xl px-6">
        @if ($heading !== '')
            <h2 class="text-center text-sm font-semibold text-gray-600">{{ $heading }}</h2>
        @endif
        @if ($logos !== [])
            <div class="mt-8 overflow-hidden mask-x-from-85% mask-x-to-100% motion-reduce:overflow-x-auto">
                <div class="inlay-logo-marquee flex w-max {{ $durationClass }}">
                    @foreach ([false, true] as $duplicate)
                        <ul class="flex shrink-0 items-center gap-x-14 pr-14" @if ($duplicate) aria-hidden="true" @endif>
                            @foreach ($logos as $logo)
                                @php
                                    $logoImage = $logo['logo'] ?? null;
                                    $hasImage = $logoImage instanceof \WeArePixel\Inlay\Support\ImageValue && ! $logoImage->isEmpty();
                                @endphp
                                <li class="flex h-10 shrink-0 items-center">
                                    @if ($hasImage)
                                        <img src="{{ $logoImage }}" alt="{{ $duplicate ? '' : ($logo['name'] ?? '') }}" class="h-8 w-auto max-w-40 object-contain opacity-70 grayscale transition hover:opacity-100 hover:grayscale-0">
                                    @else
                                        <span class="text-xl font-semibold tracking-tight whitespace-nowrap text-gray-400">{{ $logo['name'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
