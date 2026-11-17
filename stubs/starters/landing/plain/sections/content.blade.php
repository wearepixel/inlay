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
<section class="inlay-section inlay-tone-{{ $tone }}">
    <div class="inlay-container inlay-content inlay-content--{{ $width }} inlay-content--{{ $align }}">
        {{ $content }}
    </div>
</section>
