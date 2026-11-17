<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('testimonials')
        ->name('Testimonials')
        ->category('Social proof')
        ->description('Customer quotes in a grid of cards, each with the quote, the person\'s name, their role or company and an optional photo. Use real quotes only - never invent them.')
        ->keywords(['reviews', 'quotes', 'customers say', 'social proof'])
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->maxLength(90)->default('What our customers say')
                ->repeater('testimonials', Settings::make()
                    ->textarea('quote')->label('Quote')->required()->maxLength(400)
                    ->text('name')->label('Name')->required()->maxLength(80)
                    ->text('role')->label('Role or company')->maxLength(80)
                    ->image('photo')->label('Photo'))
                ->label('Testimonials')->default([])
        )
);
?>
<section class="bg-white py-20">
    <div class="mx-auto max-w-7xl px-6">
        @if ($heading !== '')
            <h2 class="text-center text-3xl font-bold tracking-tight text-gray-900 text-balance sm:text-4xl">{{ $heading }}</h2>
        @endif
        <div class="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($testimonials as $testimonial)
                <figure class="flex flex-col justify-between rounded-2xl bg-gray-50 p-8">
                    <blockquote class="text-gray-900"><p>&ldquo;{{ $testimonial['quote'] ?? '' }}&rdquo;</p></blockquote>
                    <figcaption class="mt-6 flex items-center gap-4">
                        @if (($testimonial['photo'] ?? null)?->isEmpty() === false)
                            <img src="{{ $testimonial['photo'] }}" alt="" class="h-10 w-10 rounded-full object-cover">
                        @endif
                        <div>
                            <div class="font-semibold text-gray-900">{{ $testimonial['name'] ?? '' }}</div>
                            @if (($testimonial['role'] ?? '') !== '')
                                <div class="text-sm text-gray-600">{{ $testimonial['role'] }}</div>
                            @endif
                        </div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
