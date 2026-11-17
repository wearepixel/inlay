<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('bento-features')
        ->name('Bento features')
        ->category('Content')
        ->description('A heading and intro over an asymmetric bento grid of feature cards. Each card has a title, a short description, an optional image and a size (small, wide across two columns, or tall across two rows) so the grid can be composed freely. Three columns on large screens, one on mobile.')
        ->keywords(['features', 'bento', 'grid', 'benefits', 'cards', 'capabilities', 'product tour'])
        ->settings(
            Settings::make()
                ->text('eyebrow')->label('Eyebrow')->maxLength(60)->default('Built for busy teams')
                ->text('heading')->label('Heading')->maxLength(90)->default('Everything your projects need, nothing they do not')
                ->textarea('intro')->label('Intro')->maxLength(300)->default('Plan the work, track progress and keep clients in the loop from a single workspace your whole team will actually use.')
                ->repeater('cards', Settings::make()
                    ->text('title')->label('Title')->required()->maxLength(60)
                    ->textarea('body')->label('Description')->maxLength(240)
                    ->image('image')->label('Image')
                    ->text('imageAlt')->label('Image description')->help('Leave empty if the image is decorative.')
                    ->select('size')->label('Size')->options(['small' => 'Small', 'wide' => 'Wide (two columns)', 'tall' => 'Tall (two rows)'])->default('small'))
                ->label('Cards')->default([
                    ['title' => 'Timelines that update themselves', 'body' => 'Drag a task and every dependent date moves with it. See the knock-on effect before you commit to a change.', 'size' => 'wide'],
                    ['title' => 'Client portal', 'body' => 'Share a live view of progress, files and approvals without giving clients the keys to your workspace.', 'size' => 'tall'],
                    ['title' => 'Time tracking', 'body' => 'Start a timer from any task and turn the hours into an invoice in two clicks.', 'size' => 'small'],
                    ['title' => 'Automations', 'body' => 'Assign reviewers, chase overdue work and post updates to Slack on rules you set once.', 'size' => 'small'],
                    ['title' => 'Workload view', 'body' => 'Spot who is stretched and who has room before deadlines slip, across every project at once.', 'size' => 'small'],
                    ['title' => 'Reports your clients will read', 'body' => 'Weekly summaries of what shipped, what is next and what needs a decision, sent automatically.', 'size' => 'wide'],
                ])
        )
);
?>
@php
    $sizeClasses = ['small' => '', 'wide' => 'md:col-span-2', 'tall' => 'md:row-span-2'];
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
        <ul class="mt-14 grid grid-flow-dense auto-rows-[minmax(14rem,auto)] grid-cols-1 gap-4 md:grid-cols-3 lg:gap-6">
            @foreach ($cards as $card)
                @php
                    $size = $card['size'] ?? 'small';
                    $cardImage = $card['image'] ?? null;
                    $hasImage = $cardImage instanceof \WeArePixel\Inlay\Support\ImageValue && ! $cardImage->isEmpty();
                @endphp
                <li class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-900/5 {{ $sizeClasses[$size] ?? '' }}">
                    @if ($hasImage)
                        <img src="{{ $cardImage }}" alt="{{ $card['imageAlt'] ?? '' }}" class="w-full flex-1 object-cover {{ $size === 'tall' ? 'min-h-56' : 'max-h-56 min-h-40' }}">
                    @endif
                    <div class="flex flex-col p-6 lg:p-8 {{ $hasImage ? '' : 'flex-1 justify-end' }}">
                        @unless ($hasImage)
                            <span class="mb-auto flex size-10 items-center justify-center rounded-lg bg-indigo-50 text-sm font-semibold text-indigo-600 ring-1 ring-indigo-600/10" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        @endunless
                        <h3 class="text-lg font-semibold text-gray-900 {{ $hasImage ? '' : 'mt-8' }}">{{ $card['title'] ?? '' }}</h3>
                        @if (($card['body'] ?? '') !== '')
                            <p class="mt-2 text-gray-600 text-pretty">{{ $card['body'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
