<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('faq')
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
@php($accordionId = 'faq-'.bin2hex(random_bytes(4)))
<section class="bg-body py-5">
    <div class="container py-lg-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if ($heading !== '')
                    <h2 class="display-6 fw-bold">{{ $heading }}</h2>
                @endif
                @if ($intro !== '')
                    <p class="lead text-body-secondary mt-3">{{ $intro }}</p>
                @endif
                @if ($items !== [])
                    <div class="accordion mt-4" id="{{ $accordionId }}">
                        @foreach ($items as $item)
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $accordionId }}-{{ $loop->index }}" aria-expanded="false" aria-controls="{{ $accordionId }}-{{ $loop->index }}">
                                        {{ $item['question'] ?? '' }}
                                    </button>
                                </h3>
                                <div id="{{ $accordionId }}-{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#{{ $accordionId }}">
                                    <div class="accordion-body text-body-secondary">{!! nl2br(e($item['answer'] ?? '')) !!}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
