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
<section class="inlay-section inlay-testimonials">
    <div class="inlay-container">
        @if ($heading !== '')
            <h2 class="inlay-heading inlay-center">{{ $heading }}</h2>
        @endif
        <div class="inlay-grid inlay-grid--3">
            @foreach ($testimonials as $testimonial)
                <figure class="inlay-card inlay-testimonial">
                    <blockquote><p>&ldquo;{{ $testimonial['quote'] ?? '' }}&rdquo;</p></blockquote>
                    <figcaption class="inlay-testimonial__author">
                        @if (($testimonial['photo'] ?? null)?->isEmpty() === false)
                            <img src="{{ $testimonial['photo'] }}" alt="" class="inlay-avatar">
                        @endif
                        <span>
                            <strong>{{ $testimonial['name'] ?? '' }}</strong>
                            @if (($testimonial['role'] ?? '') !== '')
                                <span class="inlay-muted">{{ $testimonial['role'] }}</span>
                            @endif
                        </span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
