<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('timeline')
        ->name('Timeline')
        ->category('Content')
        ->description('A heading and intro over a vertical timeline of milestones, each with a date or label, a title, a short description and an optional emoji marker. On large screens the entries alternate either side of a centre line; on smaller screens they stack in one column with the line on the left. Suits company history, roadmaps and step-by-step processes.')
        ->keywords(['timeline', 'history', 'milestones', 'roadmap', 'journey', 'our story', 'process', 'steps'])
        ->settings(
            Settings::make()
                ->text('eyebrow')->label('Eyebrow')->maxLength(60)->default('Our story')
                ->text('heading')->label('Heading')->maxLength(90)->default('From a side project to 4,000 teams')
                ->textarea('intro')->label('Intro')->maxLength(300)->default('Northwind started as an internal tool for a ten-person studio. These are the moments that shaped what it is today.')
                ->repeater('items', Settings::make()
                    ->text('date')->label('Date or label')->maxLength(40)
                    ->text('title')->label('Title')->required()->maxLength(80)
                    ->textarea('body')->label('Description')->maxLength(300)
                    ->text('icon')->label('Marker')->help('An optional emoji shown on the line. A dot is shown without one.')->maxLength(8))
                ->label('Milestones')->default([
                    ['date' => 'March 2019', 'title' => 'Built for ourselves', 'body' => 'Tired of chasing updates across spreadsheets, our founders built a shared board for their own studio.', 'icon' => '🛠️'],
                    ['date' => 'January 2020', 'title' => 'First paying customers', 'body' => 'Three agencies asked to use it. We opened a waiting list and onboarded the first fifty teams by hand.', 'icon' => '🤝'],
                    ['date' => 'September 2021', 'title' => 'Client portal launches', 'body' => 'Clients could finally see progress and approve work without an email thread, and approvals got four times faster.', 'icon' => '🚀'],
                    ['date' => 'June 2023', 'title' => 'SOC 2 Type II', 'body' => 'An independent audit confirmed our security controls, opening the door to larger in-house teams.', 'icon' => '🔒'],
                    ['date' => 'Today', 'title' => '4,000 teams and counting', 'body' => 'Agencies, studios and product teams in 60 countries run their projects on Northwind.', 'icon' => ''],
                ])
        )
);
?>
@php
    $items = array_values(array_filter($items, fn (array $item) => ($item['title'] ?? '') !== ''));
@endphp
<section class="bg-white py-20 sm:py-28">
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
        @if ($items !== [])
            <div class="relative mx-auto mt-16 max-w-5xl">
                <div class="absolute inset-y-0 left-5 w-px bg-gray-200 lg:left-1/2 lg:-translate-x-1/2" aria-hidden="true"></div>
                <ol class="relative space-y-12 lg:space-y-16">
                    @foreach ($items as $item)
                        @php
                            $onRight = $loop->even;
                            $icon = trim($item['icon'] ?? '');
                        @endphp
                        <li class="relative grid grid-cols-[2.5rem_1fr] gap-x-6 lg:grid-cols-[1fr_2.5rem_1fr] lg:gap-x-10">
                            <div class="relative z-10 col-start-1 row-start-1 flex size-10 items-center justify-center rounded-full bg-white ring-1 ring-gray-200 lg:col-start-2" aria-hidden="true">
                                @if ($icon !== '')
                                    <span class="text-lg leading-none">{{ $icon }}</span>
                                @else
                                    <span class="size-3 rounded-full bg-indigo-600"></span>
                                @endif
                            </div>
                            <div class="col-start-2 row-start-1 pt-1.5 {{ $onRight ? 'lg:col-start-3' : 'lg:col-start-1 lg:text-right' }}">
                                @if (($item['date'] ?? '') !== '')
                                    <p class="text-sm font-semibold text-indigo-600">{{ $item['date'] }}</p>
                                @endif
                                <h3 class="mt-1 text-lg font-semibold text-gray-900 text-balance">{{ $item['title'] }}</h3>
                                @if (($item['body'] ?? '') !== '')
                                    <p class="mt-2 text-gray-600 text-pretty">{{ $item['body'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>
</section>
