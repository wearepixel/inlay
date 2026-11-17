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
<section class="{{ ['light' => 'bg-white text-gray-900', 'muted' => 'bg-gray-50 text-gray-900', 'dark' => 'bg-gray-950 text-white'][$tone] ?? 'bg-white text-gray-900' }} py-16 lg:py-24">
    <div class="mx-auto flex flex-col gap-6 px-6 {{ $width === 'wide' ? 'max-w-5xl' : 'max-w-3xl' }} {{ $align === 'center' ? 'items-center text-center' : 'items-start' }}">
        {{ $content }}
    </div>
</section>
