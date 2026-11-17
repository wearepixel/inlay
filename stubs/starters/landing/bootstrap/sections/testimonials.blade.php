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
<section class="bg-body py-5">
    <div class="container py-lg-4">
        @if ($heading !== '')
            <h2 class="display-6 fw-bold text-center">{{ $heading }}</h2>
        @endif
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-2">
            @foreach ($testimonials as $testimonial)
                <div class="col">
                    <figure class="card h-100 border-0 bg-body-tertiary mb-0">
                        <div class="card-body d-flex flex-column justify-content-between p-4">
                            <blockquote class="mb-0"><p>&ldquo;{{ $testimonial['quote'] ?? '' }}&rdquo;</p></blockquote>
                            <figcaption class="d-flex align-items-center gap-3 mt-4">
                                @if (($testimonial['photo'] ?? null)?->isEmpty() === false)
                                    <img src="{{ $testimonial['photo'] }}" alt="" width="40" height="40" class="rounded-circle object-fit-cover">
                                @endif
                                <div>
                                    <div class="fw-semibold">{{ $testimonial['name'] ?? '' }}</div>
                                    @if (($testimonial['role'] ?? '') !== '')
                                        <div class="small text-body-secondary">{{ $testimonial['role'] }}</div>
                                    @endif
                                </div>
                            </figcaption>
                        </div>
                    </figure>
                </div>
            @endforeach
        </div>
    </div>
</section>
