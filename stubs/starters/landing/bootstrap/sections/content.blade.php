<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('content')
        ->name('Content')
        ->category('Content')
        ->description('A column editors fill with blocks - headings, text, buttons and images - in any order. Use when no purpose-built section fits.')
        ->keywords(['blocks', 'flexible', 'free-form', 'column'])
        ->settings(
            Settings::make()
                ->blocks('content')->label('Blocks')->allow(['heading', 'text', 'button', 'image'])->default([
                    ['type' => 'heading', 'settings' => ['text' => 'A heading for this section']],
                    ['type' => 'text', 'settings' => ['body' => '<p>Add, remove and reorder blocks to build this section.</p>']],
                ])
                ->select('width')->label('Width')->options(['narrow' => 'Narrow (best for reading)', 'wide' => 'Wide'])->default('narrow')
                ->select('align')->label('Alignment')->options(['left' => 'Left', 'center' => 'Centred'])->default('left')
                ->select('tone')->label('Background')->options(['light' => 'Light', 'muted' => 'Muted', 'dark' => 'Dark'])->default('light')
        )
);
?>
<section class="{{ ['light' => 'bg-body', 'muted' => 'bg-body-tertiary', 'dark' => 'bg-dark text-white'][$tone] ?? 'bg-body' }} py-5">
    <div class="container py-lg-4">
        <div class="row justify-content-center">
            <div class="{{ $width === 'wide' ? 'col-lg-10' : 'col-lg-8' }} d-flex flex-column gap-4 {{ $align === 'center' ? 'align-items-center text-center' : 'align-items-start' }}">
                {{ $content }}
            </div>
        </div>
    </div>
</section>
