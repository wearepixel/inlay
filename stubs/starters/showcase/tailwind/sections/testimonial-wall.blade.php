<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('testimonial-wall')
        ->name('Testimonial wall')
        ->category('Social proof')
        ->description('A heading and intro over a masonry wall of customer testimonials in one, two or three columns. Each card has the quote, the person\'s name, role and company, an optional photo (initials are shown without one) and an optional star rating. Mix short and long quotes so the columns stagger. Only use real quotes the business has permission to publish.')
        ->keywords(['testimonials', 'reviews', 'quotes', 'customers', 'masonry', 'wall of love', 'social proof', 'ratings'])
        ->settings(
            Settings::make()
                ->text('eyebrow')->label('Eyebrow')->maxLength(60)->default('Customer stories')
                ->text('heading')->label('Heading')->maxLength(90)->default('Teams that switched never looked back')
                ->textarea('intro')->label('Intro')->maxLength(300)->default('Agencies, studios and in-house teams use Northwind to run hundreds of projects a year. Here is what they tell us.')
                ->repeater('testimonials', Settings::make()
                    ->textarea('quote')->label('Quote')->required()->maxLength(600)
                    ->text('name')->label('Name')->required()->maxLength(60)
                    ->text('role')->label('Role')->maxLength(60)
                    ->text('company')->label('Company')->maxLength(60)
                    ->image('avatar')->label('Photo')->help('Initials are shown when there is no photo.')
                    ->select('rating')->label('Rating')->options(['none' => 'No rating', 'one' => '1 star', 'two' => '2 stars', 'three' => '3 stars', 'four' => '4 stars', 'five' => '5 stars'])->default('none'))
                ->label('Testimonials')->default([
                    ['quote' => 'We cut our weekly status meeting entirely. Everyone just checks the board.', 'name' => 'Priya Raman', 'role' => 'Operations Lead', 'company' => 'Copperleaf', 'rating' => 'five'],
                    ['quote' => 'Before Northwind, every project lived in a different spreadsheet and nobody trusted the dates. Now the timeline updates itself when a task slips, and our clients can see exactly where things stand without emailing us for an update. It has given our project managers back half a day every week.', 'name' => 'Marcus Bell', 'role' => 'Managing Director', 'company' => 'Harbour & Co', 'rating' => 'five'],
                    ['quote' => 'The client portal alone was worth the switch. Approvals that used to take a week now come back the same afternoon.', 'name' => 'Elena Sousa', 'role' => 'Account Director', 'company' => 'Brightline', 'rating' => 'five'],
                    ['quote' => 'Setup took an afternoon. Migration from our old tool took one import.', 'name' => 'Tom Okafor', 'role' => 'Head of Delivery', 'company' => 'Fieldnote', 'rating' => 'four'],
                    ['quote' => 'I was sceptical about yet another project tool, but the workload view changed how we plan. We can see who is overloaded two weeks out instead of finding out the night before a deadline. Our missed deadlines dropped by a third in the first quarter.', 'name' => 'Hannah Lindqvist', 'role' => 'Studio Manager', 'company' => 'Lumen Labs', 'rating' => 'five'],
                    ['quote' => 'Time tracking that people actually use, because it lives on the task they are already looking at.', 'name' => 'Daniel Cho', 'role' => 'Finance Manager', 'company' => 'Northpeak', 'rating' => 'none'],
                    ['quote' => 'Support answered within minutes, every time, and they clearly knew the product inside out. That matters when a client deadline is on the line.', 'name' => 'Aisha Mensah', 'role' => 'Producer', 'company' => 'Quarry', 'rating' => 'five'],
                ])
        )
);
?>
@php
    $starCounts = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
    $testimonials = array_values(array_filter($testimonials, fn (array $item) => ($item['quote'] ?? '') !== ''));
@endphp
<section class="bg-gray-50 py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            @if ($eyebrow !== '')
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">{{ $eyebrow }}</p>
            @endif
            @if ($heading !== '')
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-gray-900 text-balance sm:text-4xl">{{ $heading }}</h2>
            @endif
            @if ($intro !== '')
                <p class="mt-4 text-lg text-gray-600 text-pretty">{{ $intro }}</p>
            @endif
        </div>
        @if ($testimonials !== [])
            <ul class="mt-14 columns-1 gap-6 sm:columns-2 lg:columns-3">
                @foreach ($testimonials as $testimonial)
                    @php
                        $avatar = $testimonial['avatar'] ?? null;
                        $hasAvatar = $avatar instanceof \WeArePixel\Inlay\Support\ImageValue && ! $avatar->isEmpty();
                        $name = $testimonial['name'] ?? '';
                        $initials = collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->take(2)->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                        $stars = $starCounts[$testimonial['rating'] ?? 'none'] ?? 0;
                        $byline = implode(', ', array_filter([$testimonial['role'] ?? '', $testimonial['company'] ?? ''], fn (string $part) => $part !== ''));
                    @endphp
                    <li class="mb-6 break-inside-avoid">
                        <figure class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 lg:p-8">
                            @if ($stars > 0)
                                <div class="flex gap-0.5 text-amber-400" role="img" aria-label="Rated {{ $stars }} out of 5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <svg class="size-5 {{ $i <= $stars ? '' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401Z" clip-rule="evenodd"/></svg>
                                    @endfor
                                </div>
                            @endif
                            <blockquote class="text-gray-900 text-pretty {{ $stars > 0 ? 'mt-4' : '' }}">
                                <p>&ldquo;{{ $testimonial['quote'] }}&rdquo;</p>
                            </blockquote>
                            @if ($name !== '')
                                <figcaption class="mt-6 flex items-center gap-4">
                                    @if ($hasAvatar)
                                        <img src="{{ $avatar }}" alt="" class="size-10 shrink-0 rounded-full bg-gray-100 object-cover">
                                    @else
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 ring-1 ring-indigo-600/10" aria-hidden="true">{{ $initials }}</span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $name }}</p>
                                        @if ($byline !== '')
                                            <p class="text-sm text-gray-600">{{ $byline }}</p>
                                        @endif
                                    </div>
                                </figcaption>
                            @endif
                        </figure>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
