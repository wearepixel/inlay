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
<section class="inlay-section inlay-faq">
    <div class="inlay-container inlay-container--narrow">
        @if ($heading !== '')
            <h2 class="inlay-heading">{{ $heading }}</h2>
        @endif
        @if ($intro !== '')
            <p class="inlay-lead">{{ $intro }}</p>
        @endif
        <div class="inlay-faq__list">
            @foreach ($items as $item)
                <details class="inlay-faq__item">
                    <summary class="inlay-faq__question">{{ $item['question'] ?? '' }}</summary>
                    <p class="inlay-faq__answer">{{ $item['answer'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
