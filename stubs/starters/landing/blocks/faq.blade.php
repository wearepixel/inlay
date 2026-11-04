<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('faq')
        ->name('FAQ')
        ->category('Content')
        ->description('Frequently asked questions as expandable question-and-answer rows under a heading. Answers are plain text. Use the business\'s real policies only.')
        ->keywords(['questions', 'answers', 'accordion', 'help'])
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->maxLength(90)->default('Frequently asked questions')
                ->textarea('intro')->label('Intro')->maxLength(300)->default('')
                ->repeater('items', Settings::make()
                    ->text('question')->label('Question')->required()->maxLength(200)
                    ->textarea('answer')->label('Answer')->required()->maxLength(1500))
                ->label('Questions')->default([])
        )
);
?>
<section class="bg-white py-20">
    <div class="mx-auto max-w-3xl px-6">
        <h2 class="text-3xl font-bold tracking-tight text-gray-900 text-balance sm:text-4xl">{{ $heading }}</h2>
        @if ($intro !== '')
            <p class="mt-4 text-lg text-gray-600">{{ $intro }}</p>
        @endif
        <div class="mt-10 divide-y divide-gray-200 border-y border-gray-200">
            @foreach ($items as $item)
                <details class="group py-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-6 font-semibold text-gray-900">
                        {{ $item['question'] ?? '' }}
                        <span aria-hidden="true" class="text-xl text-gray-400 transition group-open:rotate-45">+</span>
                    </summary>
                    <p class="mt-3 whitespace-pre-line text-gray-600">{{ $item['answer'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
